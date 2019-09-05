<?php

namespace Facade\Ignition\Logger;

use Throwable;
use Monolog\Logger;
use Facade\FlareClient\Flare;
use Facade\Ignition\Ignition;
use Facade\Ignition\Tabs\Tab;
use Monolog\Handler\AbstractProcessingHandler;

class FlareHandler extends AbstractProcessingHandler
{
    /** @var \Facade\FlareClient\Flare */
    protected $flare;

    public function __construct(Flare $flare, $level = Logger::DEBUG, $bubble = true)
    {
        $this->flare = $flare;

        parent::__construct($level, $bubble);
    }

    protected function write(array $report): void
    {
        if (! $this->shouldReport($report)) {
            return;
        }

        if ($this->hasException($report)) {
            /** @var Throwable $throwable */
            $throwable = $report['context']['exception'];

            collect(Ignition::$tabs)
                ->each(function (Tab $tab) use ($throwable) {
                    $tab->beforeRenderingErrorPage($this->flare, $throwable);
                });

            $this->flare->report($report['context']['exception']);

            return;
        }

        if ($this->isErrorLog($report)) {
            $this->flare->report();
        }
    }

    protected function shouldReport(array $report): bool
    {
        return $this->hasException($report) || $this->isErrorLog($report);
    }

    protected function hasException(array $report): bool
    {
        $context = $report['context'];

        return isset($context['exception']) && $context['exception'] instanceof Throwable;
    }

    protected function isErrorLog(array $report): bool
    {
        return true;
    }
}
