<?php

namespace App\Services;

use App\Utils\CacheKey;
use App\Utils\Helper;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class AuthService
{
    /**
     * 会话闲置多久后失效（秒）。
     */
    private const SESSION_TTL = 604800; // 7 天

    /**
     * 活跃会话续期节流时间（秒）。
     * 每个会话最多每 30 分钟写一次缓存，过期精度约为 ±30 分钟。
     */
    private const SESSION_TOUCH_INTERVAL = 1800; // 30 分钟

    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function generateAuthData(Request $request, $recordLogin = true)
    {
        $guid = Helper::guid();
        $now = time();
        $authData = JWT::encode([
            'id' => $this->user->id,
            'session' => $guid,
        ], config('app.key'), 'HS256');
        if ($recordLogin) {
            $this->user->last_login_at = $now;
            $this->user->last_login_ip = $request->ip();
            $this->user->save();
        }
        self::addSession($this->user->id, $guid, [
            'ip' => $request->ip(),
            'login_at' => $now,
            'last_active_at' => $now,
            'ua' => $request->userAgent(),
            'auth_data' => $authData
        ]);
        return [
            'token' => $this->user->token,
            'is_admin' => $this->user->is_admin,
            'auth_data' => $authData
        ];
    }

    public static function decryptAuthData($jwt)
    {
        try {
            if (!is_string($jwt) || $jwt === '') {
                return false;
            }

            $cached = Cache::get($jwt);
            if (is_array($cached)
                && isset($cached['user'], $cached['uid'], $cached['session'])
            ) {
                if (!self::checkSession((int) $cached['uid'], (string) $cached['session'])) {
                    Cache::forget($jwt);
                    return false;
                }
                return $cached['user'];
            }

            $data = (array) JWT::decode($jwt, new Key(config('app.key'), 'HS256'));
            if (empty($data['id']) || empty($data['session'])) {
                return false;
            }
            if (!self::checkSession((int) $data['id'], (string) $data['session'])) {
                Cache::forget($jwt);
                return false;
            }

            $user = User::select([
                'id',
                'email',
                'is_admin',
                'is_staff'
            ])
                ->find($data['id']);
            if (!$user) return false;

            Cache::put($jwt, [
                'user' => $user->toArray(),
                'uid' => (int) $data['id'],
                'session' => (string) $data['session'],
            ], 3600);

            return $user->toArray();
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function sessionKey($userId, $session)
    {
        return CacheKey::get('USER_SESSION', $userId . '_' . $session);
    }

    private static function sessionsKey($userId)
    {
        return CacheKey::get('USER_SESSIONS', $userId);
    }

    private static function addSession($userId, $guid, $meta)
    {
        $now = time();
        $meta['login_at'] = isset($meta['login_at']) ? (int) $meta['login_at'] : $now;
        $meta['last_active_at'] = $now;
        $meta['last_touch_at'] = $now;
        $meta['expire_at'] = $now + self::SESSION_TTL;

        $indexKey = self::sessionsKey($userId);
        $sessions = (array) Cache::get($indexKey, []);

        // 清理索引里已经失效或已不存在的会话，避免活跃会话列表越积越多。
        foreach ($sessions as $sessionId => $oldMeta) {
            if ((string) $sessionId === (string) $guid) continue;
            if (Cache::has(self::sessionKey($userId, $sessionId))) continue;
            $migrated = self::migrateLegacySession($userId, $sessionId, (array) $oldMeta, false);
            if (!$migrated) {
                unset($sessions[$sessionId]);
            } else {
                $sessions[$sessionId] = $migrated;
            }
        }

        $sessions[$guid] = $meta;
        $sessionSaved = Cache::put(self::sessionKey($userId, $guid), $meta, self::SESSION_TTL);
        $indexSaved = Cache::put($indexKey, $sessions, self::SESSION_TTL);
        return $sessionSaved && $indexSaved;
    }

    private static function checkSession($userId, $session)
    {
        $sessionKey = self::sessionKey($userId, $session);
        $meta = Cache::get($sessionKey);

        // 兼容升级前只存在 USER_SESSIONS 索引里的旧会话。
        if (!is_array($meta)) {
            $meta = self::migrateLegacySession($userId, $session);
            if (!$meta) return false;
        }

        $now = time();
        if (!empty($meta['expire_at']) && (int) $meta['expire_at'] <= $now) {
            self::forgetSession($userId, $session);
            return false;
        }

        $lastTouch = (int) ($meta['last_touch_at'] ?? $meta['last_active_at'] ?? $meta['login_at'] ?? 0);
        if ($lastTouch <= 0) $lastTouch = $now;

        // 30 分钟内已经续期过就不重复写入，降低缓存写压力。
        if ($now - $lastTouch >= self::SESSION_TOUCH_INTERVAL) {
            $meta['last_active_at'] = $now;
            $meta['last_touch_at'] = $now;
            $meta['expire_at'] = $now + self::SESSION_TTL;
            Cache::put($sessionKey, $meta, self::SESSION_TTL);
            self::updateIndexMeta($userId, $session, $meta);
        }

        return true;
    }

    /**
     * 把旧版 USER_SESSIONS 中的记录迁移成独立会话 key。
     *
     * @param array|null $legacyMeta
     * @param bool $persistIndex
     * @return array|false
     */
    private static function migrateLegacySession($userId, $session, ?array $legacyMeta = null, $persistIndex = true)
    {
        $indexKey = self::sessionsKey($userId);
        $sessions = null;

        if ($legacyMeta === null) {
            $sessions = (array) Cache::get($indexKey, []);
            if (!isset($sessions[$session])) return false;
            $legacyMeta = (array) $sessions[$session];
        }
        if (!$legacyMeta) return false;

        $now = time();
        $lastActive = (int) ($legacyMeta['last_active_at'] ?? $legacyMeta['login_at'] ?? 0);
        if ($lastActive <= 0) return false;

        $expireAt = $lastActive + self::SESSION_TTL;
        if ($expireAt <= $now) return false;

        $meta = $legacyMeta;
        $meta['last_active_at'] = $lastActive;
        $meta['last_touch_at'] = $lastActive;
        $meta['expire_at'] = $expireAt;
        Cache::put(self::sessionKey($userId, $session), $meta, $expireAt - $now);

        if ($persistIndex) {
            if ($sessions === null) {
                $sessions = (array) Cache::get($indexKey, []);
            }
            $sessions[$session] = $meta;
            Cache::put($indexKey, $sessions, self::SESSION_TTL);
        }

        return $meta;
    }

    private static function updateIndexMeta($userId, $session, array $meta)
    {
        $indexKey = self::sessionsKey($userId);
        $sessions = (array) Cache::get($indexKey, []);
        $sessions[$session] = $meta;
        Cache::put($indexKey, $sessions, self::SESSION_TTL);
    }

    private static function forgetSession($userId, $session)
    {
        $sessionKey = self::sessionKey($userId, $session);
        $meta = Cache::get($sessionKey);

        if (!is_array($meta)) {
            $sessions = (array) Cache::get(self::sessionsKey($userId), []);
            $meta = isset($sessions[$session]) ? (array) $sessions[$session] : [];
        }

        if (!empty($meta['auth_data'])) {
            Cache::forget($meta['auth_data']);
        }
        Cache::forget($sessionKey);

        $indexKey = self::sessionsKey($userId);
        $sessions = (array) Cache::get($indexKey, []);
        unset($sessions[$session]);
        if (empty($sessions)) {
            Cache::forget($indexKey);
        } else {
            Cache::put($indexKey, $sessions, self::SESSION_TTL);
        }
    }

    public function getSessions()
    {
        $userId = $this->user->id;
        $indexKey = self::sessionsKey($userId);
        $sessions = (array) Cache::get($indexKey, []);
        $result = [];
        $changed = false;

        foreach ($sessions as $sessionId => $oldMeta) {
            $meta = Cache::get(self::sessionKey($userId, $sessionId));
            if (!is_array($meta)) {
                $meta = self::migrateLegacySession($userId, $sessionId, (array) $oldMeta, false);
                $changed = true;
            }
            if (!$meta) {
                unset($sessions[$sessionId]);
                $changed = true;
                continue;
            }
            $sessions[$sessionId] = $meta;
            $result[$sessionId] = $meta;
        }

        if ($changed) {
            if (empty($sessions)) {
                Cache::forget($indexKey);
            } else {
                Cache::put($indexKey, $sessions, self::SESSION_TTL);
            }
        }

        return $result;
    }

    public function removeSession($sessionId)
    {
        self::forgetSession($this->user->id, $sessionId);
        return true;
    }

    public function removeAllSession()
    {
        $userId = $this->user->id;
        $indexKey = self::sessionsKey($userId);
        $sessions = (array) Cache::get($indexKey, []);

        foreach ($sessions as $sessionId => $meta) {
            self::forgetSession($userId, $sessionId);
        }

        Cache::forget($indexKey);
        return true;
    }
}
