<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Image;

use Pimcore\Tool\Console;
use Pimcore\Tool\Session;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 */
class Chromium
{
    /**
     * @return bool
     */
    public static function isSupported()
    {
        return (bool)self::getChromiumBinary();
    }

    /**
     * @return bool
     */
    public static function getChromiumBinary()
    {
        foreach (['chromium', 'chrome'] as $app) {
            $chromium = \Pimcore\Tool\Console::getExecutable($app);
            if ($chromium) {
                return $chromium;
            }
        }

        return false;
    }

    /**
     * @param string $url
     * @param string $outputFile
     * @param string $windowSize
     *
     * @return bool
     * @throws \Exception
     */
    public static function convert(string $url, string $outputFile, string $windowSize = '1200,2000'): bool
    {

        // add parameter pimcore_preview to prevent inclusion of google analytics code, cache, etc.
        $url .= (strpos($url, '?') ? '&' : '?') . 'pimcore_preview=true';

        $options = [
            '--headless',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-extensions',
            '--ignore-certificate-errors',
            '--screenshot=' . $outputFile,
            '--window-size=' .$windowSize
        ];

        array_push($options, $url);

        // use xvfb if possible
        if ($xvfb = Console::getExecutable('xvfb-run')) {
            $command = [$xvfb, '--auto-servernum', '--server-args=-screen 0, 1280x1024x24',
                self::getChromiumBinary()];
        } else {
            $command = [self::getChromiumBinary()];
        }
        $command = array_merge($command, $options);
        Console::addLowProcessPriority($command);
        $process = new Process($command);
        //p_r($process->getCommandLine()); die;
        $process->start();

        $logHandle = fopen(PIMCORE_LOG_DIRECTORY . '/chromium.log', 'a');
        $process->wait(function ($type, $buffer) use ($logHandle) {
            fwrite($logHandle, $buffer);
        });
        fclose($logHandle);

        if (file_exists($outputFile) && filesize($outputFile) > 1000) {
            return true;
        }

        return false;
    }
}
