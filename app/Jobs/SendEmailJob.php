<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\MailLog;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $params;

    public $tries = 3;
    public $timeout = 10;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params, $queue = 'send_email')
    {
        $this->onQueue($queue);
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function applyMailConfig(): void
    {
        if (!config('v2board.email_host')) {
            return;
        }

        $panelPort = trim((string) config('v2board.email_port', ''));
        if ($panelPort !== '' && ctype_digit($panelPort)) {
            $port = (int) $panelPort;
        } else {
            $fallbackPort = trim((string) config('mail.mailers.smtp.port', ''));
            $port = ctype_digit($fallbackPort) ? (int) $fallbackPort : 587;
        }

        $encryption = strtolower(trim((string) config('v2board.email_encryption', '')));
        $useImplicitTls = in_array($encryption, ['ssl', 'smtps'], true);
        $useAutoTls = !in_array($encryption, ['', 'none'], true);

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', config('v2board.email_host', config('mail.mailers.smtp.host')));
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.mailers.smtp.scheme', $useImplicitTls ? 'smtps' : 'smtp');
        Config::set('mail.mailers.smtp.auto_tls', $useAutoTls);
        Config::set('mail.mailers.smtp.username', config('v2board.email_username', config('mail.mailers.smtp.username')));
        Config::set('mail.mailers.smtp.password', config('v2board.email_password', config('mail.mailers.smtp.password')));
        Config::set('mail.from.address', config('v2board.email_from_address', config('mail.from.address')));
        Config::set('mail.from.name', config('v2board.app_name', 'V2Board'));
    }

    public function handle()
    {
        $this->applyMailConfig();
        $params = $this->params;
        $email = $params['email'];
        $subject = $params['subject'];
        $params['template_name'] = 'mail.' . config('v2board.email_template', 'default') . '.' . $params['template_name'];
        try {
            sleep(2); 
            Mail::send(
                $params['template_name'],
                $params['template_value'],
                function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $log = [
            'email' => $params['email'],
            'subject' => $params['subject'],
            'template_name' => $params['template_name'],
            'error' => isset($error) ? $error : NULL
        ];

        MailLog::create($log);
        $log['config'] = config('mail');
        return $log;
    }
}
