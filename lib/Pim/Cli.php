<?php

namespace Blackbit\BundleEnabler\lib\Pim;

use Pimcore\Logger;
use Pimcore\Tool\Console;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class Cli
{
    /** @var null|string */
    private static $systemEnvironment = null;

    /**
     * @param string $cmd
     * @param string|null $outputFile
     *
     * @return string
     */
    public static function exec($cmd, $outputFile = null)
    {
        if (strpos($cmd, 'dd:') === 0 || strpos($cmd, 'data-director:') === 0 || strpos($cmd, 'import:') === 0) {
            $cmd = '"' . self::getPhpCli() . '" ' . realpath(PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console') . ' ' . $cmd;
        }

        $returnOutput = false;
        if ($outputFile === null) {
            $outputFile = sprintf(
                '%s/temp-file-%s.%s',
                PIMCORE_SYSTEM_TEMP_DIRECTORY,
                uniqid() . '-' . bin2hex(random_bytes(15)),
                'tmp'
            );
            $returnOutput = true;
            register_shutdown_function(static function () use ($outputFile) {
                if (file_exists($outputFile)) {
                    unlink($outputFile);
                }
            });
        }

        $cmd .= ' > "' . $outputFile . '" 2>&1';

        Logger::debug('Executing command `' . $cmd . '` on the current shell');
        chdir(PIMCORE_PROJECT_ROOT);

        if (in_array('shell_exec', explode(',', ini_get('disable_functions')), true)) {
            $process = method_exists(Process::class, 'fromShellCommandline') ? Process::fromShellCommandline($cmd, null, null, null, null) : new Process($cmd, null, null, null, null);
            $process->run();

            if ($returnOutput) {
                return $process->getOutput();
            }
        }

        shell_exec($cmd);

        if ($returnOutput) {
            return file_get_contents($outputFile);
        }
    }

    /**
     * @static
     *
     * @param string $cmd
     * @param null|string $outputFile
     *
     * @return int
     */
    public static function execInBackground($cmd, $outputFile = null)
    {
        if (strpos($cmd, 'dd:') === 0 || strpos($cmd, 'data-director:') === 0 || strpos($cmd, 'import:') === 0) {
            $cmd = '"' . self::getPhpCli() . '" ' . realpath(PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console') . ' ' . $cmd;
        }

        chdir(PIMCORE_PROJECT_ROOT);

        // windows systems
        if (self::getSystemEnvironment() === 'windows') {
            return self::execInBackgroundWindows($cmd, $outputFile);
        }

        if (self::getSystemEnvironment() === 'darwin') {
            return self::execInBackgroundUnix($cmd, $outputFile, false);
        }

        return self::execInBackgroundUnix($cmd, $outputFile);
    }

    protected static function execInBackgroundUnix($cmd, $outputFile, $useNohup = true)
    {
        if (!$outputFile) {
            $outputFile = '/dev/null';
        }

        $nice = (string)self::getExecutable('nice');
        if ($nice) {
            $nice .= ' -n 19 ';
        }

        if ($useNohup) {
            $nohup = (string)self::getExecutable('nohup');
            if ($nohup) {
                $nohup .= ' ';
            }
        } else {
            $nohup = '';
        }

        /**
         * mod_php seems to lose the environment variables if we do not set them manually before the child process is started
         */
        if (strpos(php_sapi_name(), 'apache') !== false) {
            foreach (['PIMCORE_ENVIRONMENT', 'APP_ENV'] as $envVarName) {
                if ($envValue = $_SERVER[$envVarName] ?? $_SERVER['REDIRECT_' . $envVarName] ?? null) {
                    putenv($envVarName . '=' . $envValue);
                }
            }
        }

        $commandWrapped = $nohup . $nice . $cmd . ' > ' . $outputFile . ' 2>&1 & echo $!';
        Logger::debug('Executing command `' . $commandWrapped . '´ on the current shell in background');

        if (in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            $process = method_exists(Process::class, 'fromShellCommandline') ? Process::fromShellCommandline($commandWrapped, null, null, null, null) : new Process($commandWrapped, null, null, null, null);
            $process->start();
            $pid = $process->getPid();
            $process->wait();
        } else {
            $pid = shell_exec($commandWrapped);
        }

        return (int)$pid;
    }

    /**
     * @static
     *
     * @param string $cmd
     * @param string $outputFile
     *
     * @return int
     */
    protected static function execInBackgroundWindows($cmd, $outputFile)
    {
        if (!$outputFile) {
            $outputFile = 'NUL';
        }

        $commandWrapped = 'cmd /c ' . $cmd . ' > ' . $outputFile . ' 2>&1';
        Logger::debug('Executing command `' . $commandWrapped . '´ on the current shell in background');

        $WshShell = new \COM('WScript.Shell');
        $WshShell->Run($commandWrapped, 0, false);
        // returning the PID is not supported on Windows Systems

        return 0;
    }

    public static function getPhpCli()
    {
        try {
            if (\Pimcore::getContainer()->hasParameter('pimcore_executable_php')) {
                $executablePath = \Pimcore::getContainer()->getParameter('pimcore_executable_php');

                if ($executablePath) {
                    return $executablePath;
                }
            }

            $phpFinder = new PhpExecutableFinder();
            $phpPath = $phpFinder->find(true);
            if ($phpPath) {
                return $phpPath;
            }

            $executablePath = Console::getPhpCli();
            if ($executablePath) {
                return $executablePath;
            }
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        } catch (\Exception $e) {
            $checkCmd = 'which php';
            if (self::getSystemEnvironment() === 'windows') {
                $checkCmd = 'where php';
            }

            if (in_array('shell_exec', explode(',', ini_get('disable_functions')), true)) {
                symfonyProcess:
                $process = method_exists(Process::class, 'fromShellCommandline') ? Process::fromShellCommandline($checkCmd, null, null, null, null) : new Process($checkCmd, null, null, null, null);
                $process->run();
                $executablePath = $process->getOutput();
            } else {
                $executablePath = shell_exec($checkCmd);

                if (!$executablePath) {
                    goto symfonyProcess;
                }
            }

            $executablePath = trim(strtok($executablePath, "\n")); // get the first line/result

            if ($executablePath) {
                return $executablePath;
            }

            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }
    }

    /**
     * @return string
     */
    private static function getSystemEnvironment()
    {
        if (self::$systemEnvironment === null) {
            if (stripos(php_uname('s'), 'windows') !== false) {
                self::$systemEnvironment = 'windows';
            } elseif (stripos(php_uname('s'), 'darwin') !== false) {
                self::$systemEnvironment = 'darwin';
            } else {
                self::$systemEnvironment = 'unix';
            }
        }

        return self::$systemEnvironment;
    }

    public static function getExecutable($name, $throwException = false)
    {
        $executable = null;
        try {
            $executable = Console::getExecutable($name, $throwException);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'executable was disabled manually') !== false) {
                throw $e;
            }
        }

        if (!$executable) {
            $checkCmd = 'which ' . escapeshellarg($name);

            if (in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
                $process = method_exists(Process::class, 'fromShellCommandline') ? Process::fromShellCommandline($checkCmd, null, null, null, null) : new Process($checkCmd, null, null, null, null);
                $process->run();
                $executablePath = $process->getOutput();
            } else {
                $executablePath = shell_exec($checkCmd);
            }

            $executable = trim(strtok($executablePath, "\n"));
        }

        if (!$executable && $throwException) {
            throw new \Exception("No '$executable' executable found, please install the application or add it to the PATH (in system settings or to your PATH environment variable)");
        }

        return $executable;
    }
}
