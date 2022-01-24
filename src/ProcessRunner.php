<?php declare(strict_types=1);

namespace seregazhuk\PhpWatcher;

use React\ChildProcess\Process as ReactPHPProcess;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;
use seregazhuk\PhpWatcher\Screen\Screen;
use function React\Promise\resolve;

final class ProcessRunner
{
    private $loop;

    private $screen;

    private $process;

    public function __construct(LoopInterface $loop, Screen $screen, string $command)
    {
        $this->loop = $loop;
        $this->screen = $screen;
        $this->process = new ReactPHPProcess($command);
    }

    public function start(): void
    {
        $this->screen->start($this->process->getCommand());
        $this->screen->showSpinner($this->loop);

        $this->process->start($this->loop);
        $this->subscribeToProcessOutput();
    }

    public function stop(int $signal): PromiseInterface
    {
        if (false === $this->process->isRunning()) {
            return resolve();
        }

        $defered = new Deferred();

        $exitHandler = function () use ($defered, &$exitHandler) {
            $this->process->removeAllListeners();
            $defered->resolve();
        };
        $this->process->on('exit', $exitHandler);
        $this->process->terminate($signal);

        return $defered->promise();
    }

    public function restart(float $delayToRestart): void
    {
        $this->screen->restarting();
        $this->loop->addTimer($delayToRestart, [$this, 'start']);
    }

    private function subscribeToProcessOutput(): void
    {
        if ($this->process->stdout === null || $this->process->stderr === null) {
            throw new RuntimeException('Cannot open I/O for a process');
        }

        $this->process->stdout->on('data', [$this->screen, 'plainOutput']);
        $this->process->stderr->on('data', [$this->screen, 'plainOutput']);
        $this->process->on('exit', [$this->screen, 'processExit']);
    }
}
