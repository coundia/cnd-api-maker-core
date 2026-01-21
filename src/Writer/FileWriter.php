<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Writer;

use Symfony\Component\Filesystem\Filesystem;

class FileWriter
{
	private Filesystem $fs;

	public function __construct()
	{
		$this->fs = new Filesystem();
	}

	public function write(string $path, string $content, bool $force, bool $dryRun): void
	{

		if ($dryRun) {
			print_r('DRY '.$path.PHP_EOL);
			return;
		}

		$dir = dirname($path);
		$this->fs->mkdir($dir);

		if (is_file($path) && !$force) {
			print_r('SKIP '.$path.PHP_EOL);
			return;
		}

		$this->fs->dumpFile($path, $content);
	//	print_r('WRITE '.$path.PHP_EOL);
	}

    public function appendToLaravelRoutes(string $path, string $useLine, string $routeBlock, string $needle, bool $dryRun): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !$dryRun) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($path)) {
            $content = "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\\Support\\Facades\\Route;\n\n".$useLine."\n\n".$routeBlock."\n";
            if (!$dryRun) {
                file_put_contents($path, $content);
            }
            return;
        }

        $existing = (string) file_get_contents($path);

        if (str_contains($existing, $needle)) {
            return;
        }

        $out = $existing;

        if (!str_contains($out, $useLine)) {
            $pos = strpos($out, "use Illuminate\\Support\\Facades\\Route;");
            if ($pos !== false) {
                $insertAt = $pos + strlen("use Illuminate\\Support\\Facades\\Route;");
                $out = substr($out, 0, $insertAt)."\n".$useLine.substr($out, $insertAt);
            } else {
                $out = rtrim($out)."\n\n".$useLine."\n";
            }
        }

        $out = rtrim($out)."\n\n".$routeBlock."\n";

        if (!$dryRun) {
            file_put_contents($path, $out);
        }
    }

}
