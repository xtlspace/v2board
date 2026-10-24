<?php
namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use App\Models\Log as LogModel;

class MysqlLoggerHandler extends AbstractProcessingHandler
{
    public function __construct($level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try{
            $context = $record->context;
            if(isset($context['exception']) && is_object($context['exception'])){
                $context['exception'] = (array)$context['exception'];
            }
            $extra = $record->extra;
            // 请求上下文可能不可用（例如容器在请求执行过程中被替换），取不到时降级，避免日志器自身抛异常掩盖原始异常
            $request = null;
            try {
                $request = request();
            } catch (\Throwable $e) {
                $request = null;
            }
            $requestData = $request ? ($request->all() ?? []) : [];
            $log = [
                'title' => $record->message,
                'level' => $record->level->getName(),
                'host' => $extra['request_host'] ?? $context['request_host'] ?? ($request ? $request->getSchemeAndHttpHost() : '-'),
                'uri' => $extra['request_uri'] ?? $context['request_uri'] ?? ($request ? $request->getRequestUri() : '-'),
                'method' => $extra['request_method'] ?? $context['request_method'] ?? ($request ? $request->getMethod() : '-'),
                'ip' => $request ? $request->getClientIp() : '-',
                'data' => json_encode($requestData) ,
                'context' => $context ? json_encode($context) : '',
                'created_at' => $record->datetime->getTimestamp(),
                'updated_at' => $record->datetime->getTimestamp(),
            ];

            LogModel::insert(
                $log
            );
        }catch (\Throwable $e){
            Log::channel('daily')->error($e->getMessage().$e->getFile().$e->getTraceAsString());
        }
    }
}
