<?php

declare(strict_types=1);

namespace Tests\Application\Unit;

use Internal\Path;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Assert\ExpectException;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Application::class)]
final class ApplicationTest
{
    #[ExpectException(\InvalidArgumentException::class)]
    public function throwsWhenConfigFileReturnsWrongType(): void
    {
        $tmp = \sys_get_temp_dir() . '/testo_cfg_' . \uniqid('', true) . '.php';
        \file_put_contents($tmp, '<?php return null;');
        try {
            $app = Application::createFromInput(Path::create($tmp));
            $app->getContainer()->get(ApplicationConfig::class);
        } finally {
            \is_file($tmp) and \unlink($tmp);
        }
    }
}
