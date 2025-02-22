<?php
/**
 * A class to process command line phpcs scripts.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

error_reporting(E_ALL | E_STRICT);

// Make sure version id constant is available.
if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    define('PHP_VERSION_ID', (int) (($version[0] * 10000) + ($version[1] * 100) + $version[2]));
    unset($version);
}

// Make sure that we autoload all dependencies if running via Composer.
if (PHP_VERSION_ID >= 50302) {
    if (file_exists($a = dirname(__FILE__).'/../../../autoload.php')) {
        include_once $a;
    } elseif (file_exists($a = dirname(__FILE__).'/../vendor/autoload.php')) {
        include_once $a;
    }
}

if (file_exists($a = dirname(__FILE__).'/../CodeSniffer.php')) {
    // Running from a git clone.
    include_once $a;
} else {
    // PEAR installed.
    include_once __DIR__ . '/PHP/CodeSniffer.php';
}

/**
 * A class to process command line phpcs scripts.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PHP_CodeSniffer_CLI
{

    /**
     * An array of all values specified on the command line.
     */
    protected array $values = array();

    /**
     * The minimum severity level errors must have to be displayed.
     */
    public int $errorSeverity = 0;

    /**
     * The minimum severity level warnings must have to be displayed.
     */
    public int $warningSeverity = 0;

    /**
     * Whether or not to kill the process when an unknown command line arg is found.
     *
     * If FALSE, arguments that are not command line options or file/directory paths
     * will be ignored and execution will continue.
     */
    public bool $dieOnUnknownArg = true;

    /**
     * An array of the current command line arguments we are processing.
     */
    private array $_cliArgs = array();


    /**
     * Run the PHPCS script.
     *
     * @return array
     */
    public function runphpcs()
    {
        if (!defined('PHP_CODESNIFFER_CBF')) {
            define('PHP_CODESNIFFER_CBF', false);
        }

        if (is_file(dirname(__FILE__).'/../CodeSniffer/Reporting.php')) {
            include_once dirname(__FILE__).'/../CodeSniffer/Reporting.php';
        } else {
            include_once __DIR__ . '/PHP/CodeSniffer/Reporting.php';
        }

        PHP_CodeSniffer_Reporting::startTiming();
        $this->checkRequirements();
        $numErrors = $this->process();
        if ($numErrors === 0) {
            exit(0);
        } else {
            exit(1);
        }

    }//end runphpcs()


    /**
     * Run the PHPCBF script.
     *
     * @return array
     */
    public function runphpcbf()
    {
        if (!defined('PHP_CODESNIFFER_CBF')) {
            define('PHP_CODESNIFFER_CBF', true);
        }

        if (is_file(dirname(__FILE__).'/../CodeSniffer/Reporting.php')) {
            include_once dirname(__FILE__).'/../CodeSniffer/Reporting.php';
        } else {
            include_once __DIR__ . '/PHP/CodeSniffer/Reporting.php';
        }

        PHP_CodeSniffer_Reporting::startTiming();
        $this->checkRequirements();

        $this->dieOnUnknownArg = false;

        // Override some of the command line settings that might break the fixes.
        $cliValues = $this->getCommandLineValues();
        $cliValues['verbosity']    = 0;
        $cliValues['showProgress'] = false;
        $cliValues['generator']    = '';
        $cliValues['explain']      = false;
        $cliValues['interactive']  = false;
        $cliValues['showSources']  = false;
        $cliValues['reportFile']   = null;
        $cliValues['reports']      = array();

        $suffix = '';
        if (isset($cliValues['suffix'])) {
            $suffix = $cliValues['suffix'];
        }

        $allowPatch = true;
        if (isset($cliValues['no-patch']) || empty($cliValues['files'])) {
            // They either asked for this,
            // or they are using STDIN, which can't use diff.
            $allowPatch = false;
        }

        if ($suffix === '' && $allowPatch) {
            // Using the diff/patch tools.
            $diffFile = getcwd().'/phpcbf-fixed.diff';
            $cliValues['reports'] = array('diff' => $diffFile);
            if (file_exists($diffFile)) {
                unlink($diffFile);
            }
        } else {
            // Replace the file without the patch command
            // or writing to a file with a new suffix.
            $cliValues['reports']       = array('cbf' => null);
            $cliValues['phpcbf-suffix'] = $suffix;
        }

        $numErrors = $this->process($cliValues);

        if ($suffix === '' && $allowPatch) {
            if (!file_exists($diffFile)) {
                // Nothing to fix.
                $exit = $numErrors === 0 ? 0 : 2;
            } else {
                if (filesize($diffFile) < 10) {
                    // Empty or bad diff file.
                    $exit = $numErrors === 0 ? 0 : 2;
                } else {
                    $cmd    = "patch -p0 -ui \"$diffFile\"";
                    $output = array();
                    $retVal = null;
                    exec($cmd, $output, $retVal);

                    if ($retVal === 0) {
                        // Everything went well.
                        $filesPatched = count($output);
                        echo "Patched $filesPatched file";
                        if ($filesPatched > 1) {
                            echo 's';
                        }

                        echo PHP_EOL;
                        $exit = 1;
                    } else {
                        print_r($output);
                        echo "Returned: $retVal".PHP_EOL;
                        $exit = 3;
                    }
                }//end if

                unlink($diffFile);
            }//end if
        } else {
            // File are being patched manually, so we can't tell
            // how many errors were fixed.
            $exit = 1;
        }//end if

        if ($exit === 0) {
            echo 'No fixable errors were found'.PHP_EOL;
        } elseif ($exit === 2) {
            echo 'PHPCBF could not fix all the errors found'.PHP_EOL;
        }

        PHP_CodeSniffer_Reporting::printRunTime();
        exit($exit);

    }//end runphpcbf()


    /**
     * Exits if the minimum requirements of PHP_CodSniffer are not met.
     *
     * @return array
     */
    public function checkRequirements()
    {
        // Check the PHP version.
        if (PHP_VERSION_ID < 50102) {
            echo 'ERROR: PHP_CodeSniffer requires PHP version 5.1.2 or greater.'.PHP_EOL;
            exit(2);
        }

        if (!extension_loaded('tokenizer')) {
            echo 'ERROR: PHP_CodeSniffer requires the tokenizer extension to be enabled.'.PHP_EOL;
            exit(2);
        }

    }//end checkRequirements()


    /**
     * Get a list of default values for all possible command line arguments.
     *
     * @return array
     */
    public function getDefaults()
    {
        if (defined('PHP_CODESNIFFER_IN_TESTS')) {
            return array();
        }

        // The default values for config settings.
        $defaults['files']           = array();
        $defaults['standard']        = null;
        $defaults['verbosity']       = 0;
        $defaults['interactive']     = false;
        $defaults['colors']          = false;
        $defaults['explain']         = false;
        $defaults['local']           = false;
        $defaults['showSources']     = false;
        $defaults['extensions']      = array();
        $defaults['sniffs']          = array();
        $defaults['exclude']         = array();
        $defaults['ignored']         = array();
        $defaults['reportFile']      = null;
        $defaults['generator']       = '';
        $defaults['reports']         = array();
        $defaults['bootstrap']       = array();
        $defaults['errorSeverity']   = null;
        $defaults['warningSeverity'] = null;
        $defaults['stdin']           = null;
        $defaults['stdinPath']       = '';

        $reportFormat = PHP_CodeSniffer::getConfigData('report_format');
        if ($reportFormat !== null) {
            $defaults['reports'][$reportFormat] = null;
        }

        $tabWidth = PHP_CodeSniffer::getConfigData('tab_width');
        $defaults['tabWidth'] = $tabWidth === null ? 0 : (int) $tabWidth;

        $encoding = PHP_CodeSniffer::getConfigData('encoding');
        $defaults['encoding'] = $encoding === null ? 'iso-8859-1' : strtolower($encoding);

        $severity = PHP_CodeSniffer::getConfigData('severity');
        if ($severity !== null) {
            $defaults['errorSeverity']   = (int) $severity;
            $defaults['warningSeverity'] = (int) $severity;
        }

        $severity = PHP_CodeSniffer::getConfigData('error_severity');
        if ($severity !== null) {
            $defaults['errorSeverity'] = (int) $severity;
        }

        $severity = PHP_CodeSniffer::getConfigData('warning_severity');
        if ($severity !== null) {
            $defaults['warningSeverity'] = (int) $severity;
        }

        $showWarnings = PHP_CodeSniffer::getConfigData('show_warnings');
        if ($showWarnings !== null) {
            $showWarnings = (bool) $showWarnings;
            if (!$showWarnings) {
                $defaults['warningSeverity'] = 0;
            }
        }

        $reportWidth = PHP_CodeSniffer::getConfigData('report_width');
        $defaults['reportWidth'] = $reportWidth !== null ? $this->_validateReportWidth($reportWidth) : null;

        $showProgress = PHP_CodeSniffer::getConfigData('show_progress');
        $defaults['showProgress'] = $showProgress === null ? false : (bool) $showProgress;

        $quiet = PHP_CodeSniffer::getConfigData('quiet');
        $defaults['quiet'] = $quiet === null ? false : (bool) $quiet;

        $colors = PHP_CodeSniffer::getConfigData('colors');
        $defaults['colors'] = $colors === null ? false : (bool) $colors;

        if (PHP_CodeSniffer::isPharFile(dirname(dirname(__FILE__))) === true) {
            // If this is a phar file, check for the standard in the config.
            $standard = PHP_CodeSniffer::getConfigData('standard');
            if ($standard !== null) {
                $defaults['standard'] = $standard;
            }
        }

        return $defaults;

    }//end getDefaults()


    /**
     * Gets the processed command line values.
     *
     * If the values have not yet been set, the values will be sourced
     * from the command line arguments.
     *
     * @return array
     */
    public function getCommandLineValues()
    {
        if (!empty($this->values)) {
            return $this->values;
        }

        $args = $_SERVER['argv'];
        array_shift($args);

        $this->setCommandLineValues($args);

        // Check for content on STDIN.
        $handle = fopen('php://stdin', 'r');
        if (stream_set_blocking($handle, false)) {
            $fileContents = '';
            while (($line = fgets($handle)) !== false) {
                $fileContents .= $line;
                usleep(10);
            }

            stream_set_blocking($handle, true);
            fclose($handle);
            if (trim($fileContents) !== '') {
                $this->values['stdin'] = $fileContents;
            }
        }

        return $this->values;

    }//end getCommandLineValues()


    /**
     * Set the command line values.
     *
     * @param array $args An array of command line arguments to process.
     *
     * @return void
     */
    public function setCommandLineValues($args)
    {
        if (defined('PHP_CODESNIFFER_IN_TESTS')) {
            $this->values = array(
                             'stdin' => null,
                             'quiet' => true,
                            );
        } elseif (empty($this->values)) {
            $this->values = $this->getDefaults();
        }

        $this->_cliArgs = $args;
        $numArgs        = count($args);

        for ($i = 0; $i < $numArgs; $i++) {
            $arg = $this->_cliArgs[$i];
            if ($arg === '') {
                continue;
            }

            if ($arg[0] === '-') {
                if ($arg === '-' || $arg === '--') {
                    // Empty argument, ignore it.
                    continue;
                }

                if ($arg[1] === '-') {
                    $this->processLongArgument(substr($arg, 2), $i);
                } else {
                    $switches = str_split($arg);
                    foreach ($switches as $switch) {
                        if ($switch === '-') {
                            continue;
                        }

                        $this->processShortArgument($switch, $i);
                    }
                }
            } else {
                $this->processUnknownArgument($arg, $i);
            }//end if
        }//end for

    }//end setCommandLineValues()


    /**
     * Processes a short (-e) command line argument.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     */
    public function processShortArgument($arg, $pos)
    {
        switch ($arg) {
        case 'h':
        case '?':
            $this->printUsage();
            exit(0);
        case 'i' :
            $this->printInstalledStandards();
            exit(0);
        case 'v' :
            if ($this->values['quiet'] === true) {
                // Ignore when quiet mode is enabled.
                break;
            }

            if (!isset($this->values['verbosity'])) {
                $this->values['verbosity'] = 1;
            } else {
                $this->values['verbosity']++;
            }
            break;
        case 'l' :
            $this->values['local'] = true;
            break;
        case 's' :
            $this->values['showSources'] = true;
            break;
        case 'a' :
            $this->values['interactive'] = true;
            break;
        case 'e':
            $this->values['explain'] = true;
            break;
        case 'p' :
            if ($this->values['quiet'] === true) {
                // Ignore when quiet mode is enabled.
                break;
            }

            $this->values['showProgress'] = true;
            break;
        case 'q' :
            // Quiet mode disables a few other settings as well.
            $this->values['quiet']        = true;
            $this->values['showProgress'] = false;
            $this->values['verbosity']    = 0;
            break;
        case 'd' :
            $ini = explode('=', $this->_cliArgs[($pos + 1)]);
            $this->_cliArgs[($pos + 1)] = '';
            if (isset($ini[1])) {
                ini_set($ini[0], $ini[1]);
            } else {
                ini_set($ini[0], true);
            }
            break;
        case 'n' :
            $this->values['warningSeverity'] = 0;
            break;
        case 'w' :
            $this->values['warningSeverity'] = null;
            break;
        default:
            if (!$this->dieOnUnknownArg) {
                $this->values[$arg] = $arg;
            } else {
                $this->processUnknownArgument('-'.$arg, $pos);
            }
        }//end switch

    }//end processShortArgument()


    /**
     * Processes a long (--example) command line argument.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     */
    public function processLongArgument($arg, $pos)
    {
        switch ($arg) {
        case 'help':
            $this->printUsage();
            exit(0);
        case 'version':
            echo 'PHP_CodeSniffer version '.PHP_CodeSniffer::VERSION.' ('.PHP_CodeSniffer::STABILITY.') ';
            echo 'by Squiz (http://www.squiz.net)'.PHP_EOL;
            exit(0);
        case 'colors':
            $this->values['colors'] = true;
            break;
        case 'no-colors':
            $this->values['colors'] = false;
            break;
        case 'config-set':
            if (!isset($this->_cliArgs[($pos + 1)])
                || !isset($this->_cliArgs[($pos + 2)])
            ) {
                echo 'ERROR: Setting a config option requires a name and value'.PHP_EOL.PHP_EOL;
                $this->printUsage();
                exit(0);
            }

            $key     = $this->_cliArgs[($pos + 1)];
            $value   = $this->_cliArgs[($pos + 2)];
            $current = PHP_CodeSniffer::getConfigData($key);

            try {
                PHP_CodeSniffer::setConfigData($key, $value);
            } catch (Exception $e) {
                echo $e->getMessage().PHP_EOL;
                exit(2);
            }

            if ($current === null) {
                echo "Config value \"$key\" added successfully".PHP_EOL;
            } else {
                echo "Config value \"$key\" updated successfully; old value was \"$current\"".PHP_EOL;
            }
            exit(0);
        case 'config-delete':
            if (!isset($this->_cliArgs[($pos + 1)])) {
                echo 'ERROR: Deleting a config option requires the name of the option'.PHP_EOL.PHP_EOL;
                $this->printUsage();
                exit(0);
            }

            $key     = $this->_cliArgs[($pos + 1)];
            $current = PHP_CodeSniffer::getConfigData($key);
            if ($current === null) {
                echo "Config value \"$key\" has not been set".PHP_EOL;
            } else {
                try {
                    PHP_CodeSniffer::setConfigData($key, null);
                } catch (Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                    exit(2);
                }

                echo "Config value \"$key\" removed successfully; old value was \"$current\"".PHP_EOL;
            }
            exit(0);
        case 'config-show':
            $data = PHP_CodeSniffer::getAllConfigData();
            $this->printConfigData($data);
            exit(0);
        case 'runtime-set':
            if (!isset($this->_cliArgs[($pos + 1)])
                || !isset($this->_cliArgs[($pos + 2)])
            ) {
                echo 'ERROR: Setting a runtime config option requires a name and value'.PHP_EOL.PHP_EOL;
                $this->printUsage();
                exit(0);
            }

            $key   = $this->_cliArgs[($pos + 1)];
            $value = $this->_cliArgs[($pos + 2)];
            $this->_cliArgs[($pos + 1)] = '';
            $this->_cliArgs[($pos + 2)] = '';
            PHP_CodeSniffer::setConfigData($key, $value, true);
            break;
        default:
            if (substr($arg, 0, 7) === 'sniffs=') {
                $sniffs = explode(',', substr($arg, 7));
                foreach ($sniffs as $sniff) {
                    if (substr_count($sniff, '.') !== 2) {
                        echo 'ERROR: The specified sniff code "'.$sniff.'" is invalid'.PHP_EOL.PHP_EOL;
                        $this->printUsage();
                        exit(2);
                    }
                }
                $this->values['sniffs'] = $sniffs;
            } elseif (substr($arg, 0, 8) === 'exclude=') {
                $sniffs = explode(',', substr($arg, 8));
                foreach ($sniffs as $sniff) {
                    if (substr_count($sniff, '.') !== 2) {
                        echo 'ERROR: The specified sniff code "'.$sniff.'" is invalid'.PHP_EOL.PHP_EOL;
                        $this->printUsage();
                        exit(2);
                    }
                }
                $this->values['exclude'] = $sniffs;
            } elseif (substr($arg, 0, 10) === 'bootstrap=') {
                $files = explode(',', substr($arg, 10));
                foreach ($files as $file) {
                    $path = PHP_CodeSniffer::realpath($file);
                    if ($path === false) {
                        echo 'ERROR: The specified bootstrap file "'.$file.'" does not exist'.PHP_EOL.PHP_EOL;
                        $this->printUsage();
                        exit(2);
                    }

                    $this->values['bootstrap'][] = $path;
                }
            } elseif (substr($arg, 0, 10) === 'file-list=') {
                $fileList = substr($arg, 10);
                $path     = PHP_CodeSniffer::realpath($fileList);
                if ($path === false) {
                    echo 'ERROR: The specified file list "'.$file.'" does not exist'.PHP_EOL.PHP_EOL;
                    $this->printUsage();
                    exit(2);
                }
                $files = file($path);
                foreach ($files as $inputFile) {
                    $inputFile = trim($inputFile);

                    // Skip empty lines.
                    if ($inputFile === '') {
                        continue;
                    }

                    $realFile = PHP_CodeSniffer::realpath($inputFile);
                    if ($realFile === false) {
                        echo 'ERROR: The specified file "'.$inputFile.'" does not exist'.PHP_EOL.PHP_EOL;
                        $this->printUsage();
                        exit(2);
                    }

                    $this->values['files'][] = $realFile;
                }
            } elseif (substr($arg, 0, 11) === 'stdin-path=') {
                $this->values['stdinPath'] = PHP_CodeSniffer::realpath(substr($arg, 11));
                // It may not exist and return false instead, so just use whatever they gave us.
                if ($this->values['stdinPath'] === false) {
                    $this->values['stdinPath'] = trim(substr($arg, 11));
                }
            } elseif (substr($arg, 0, 12) === 'report-file=') {
                $this->values['reportFile'] = PHP_CodeSniffer::realpath(substr($arg, 12));
                // It may not exist and return false instead.
                if ($this->values['reportFile'] === false) {
                    $this->values['reportFile'] = substr($arg, 12);

                    $dir = dirname($this->values['reportFile']);
                    if (!is_dir($dir)) {
                        echo 'ERROR: The specified report file path "'.$this->values['reportFile'].'" points to a non-existent directory'.PHP_EOL.PHP_EOL;
                        $this->printUsage();
                        exit(2);
                    }

                    if ($dir === '.') {
                        // Passed report file is a file in the current directory.
                        $this->values['reportFile'] = getcwd().'/'.basename($this->values['reportFile']);
                    } else {
                        $dir = $dir[0] === '/' ? PHP_CodeSniffer::realpath($dir) : PHP_CodeSniffer::realpath(getcwd().'/'.$dir);

                        if ($dir !== false) {
                            // Report file path is relative.
                            $this->values['reportFile'] = $dir.'/'.basename($this->values['reportFile']);
                        }
                    }
                }
                //end if
                if (is_dir($this->values['reportFile'])) {
                    echo 'ERROR: The specified report file path "'.$this->values['reportFile'].'" is a directory'.PHP_EOL.PHP_EOL;
                    $this->printUsage();
                    exit(2);
                }
            } elseif (substr($arg, 0, 13) === 'report-width=') {
                $this->values['reportWidth'] = $this->_validateReportWidth(substr($arg, 13));
            } elseif (substr($arg, 0, 7) === 'report='
                || substr($arg, 0, 7) === 'report-') {
                if ($arg[6] === '-') {
                    // This is a report with file output.
                    $split = strpos($arg, '=');
                    if ($split === false) {
                        $report = substr($arg, 7);
                        $output = null;
                    } else {
                        $report = substr($arg, 7, ($split - 7));
                        $output = substr($arg, ($split + 1));
                        if ($output === false) {
                            $output = null;
                        } else {
                            $dir = dirname($output);
                            if ($dir === '.') {
                                // Passed report file is a filename in the current directory.
                                $output = getcwd().'/'.basename($output);
                            } else {
                                $dir = $dir[0] === '/' ? PHP_CodeSniffer::realpath($dir) : PHP_CodeSniffer::realpath(getcwd().'/'.$dir);

                                if ($dir !== false) {
                                    // Report file path is relative.
                                    $output = $dir.'/'.basename($output);
                                }
                            }
                        }//end if
                    }//end if
                } else {
                    // This is a single report.
                    $report = substr($arg, 7);
                    $output = null;
                }
                //end if
                $this->values['reports'][$report] = $output;
            } elseif (substr($arg, 0, 9) === 'standard=') {
                $standards = trim(substr($arg, 9));
                if ($standards !== '') {
                    $this->values['standard'] = explode(',', $standards);
                }
            } elseif (substr($arg, 0, 11) === 'extensions=') {
                if (!isset($this->values['extensions'])) {
                    $this->values['extensions'] = array();
                }
                $this->values['extensions'] = array_merge($this->values['extensions'], explode(',', substr($arg, 11)));
            } elseif (substr($arg, 0, 9) === 'severity=') {
                $this->values['errorSeverity']   = (int) substr($arg, 9);
                $this->values['warningSeverity'] = $this->values['errorSeverity'];
            } elseif (substr($arg, 0, 15) === 'error-severity=') {
                $this->values['errorSeverity'] = (int) substr($arg, 15);
            } elseif (substr($arg, 0, 17) === 'warning-severity=') {
                $this->values['warningSeverity'] = (int) substr($arg, 17);
            } elseif (substr($arg, 0, 7) === 'ignore=') {
                // Split the ignore string on commas, unless the comma is escaped
                // using 1 or 3 slashes (\, or \\\,).
                $ignored = preg_split(
                    '/(?<=(?<!\\\\)\\\\\\\\),|(?<!\\\\),/',
                    substr($arg, 7)
                );
                foreach ($ignored as $pattern) {
                    $pattern = trim($pattern);
                    if ($pattern === '') {
                        continue;
                    }

                    $this->values['ignored'][$pattern] = 'absolute';
                }
            } elseif (substr($arg, 0, 10) === 'generator=') {
                $this->values['generator'] = substr($arg, 10);
            } elseif (substr($arg, 0, 9) === 'encoding=') {
                $this->values['encoding'] = strtolower(substr($arg, 9));
            } elseif (substr($arg, 0, 10) === 'tab-width=') {
                $this->values['tabWidth'] = (int) substr($arg, 10);
            } elseif (!$this->dieOnUnknownArg) {
                $eqPos = strpos($arg, '=');
                if ($eqPos === false) {
                    $this->values[$arg] = $arg;
                } else {
                    $value = substr($arg, ($eqPos + 1));
                    $arg   = substr($arg, 0, $eqPos);
                    $this->values[$arg] = $value;
                }
            } else {
                $this->processUnknownArgument('--'.$arg, $pos);
            }//end if

            break;
        }//end switch

    }//end processLongArgument()


    /**
     * Processes an unknown command line argument.
     *
     * Assumes all unknown arguments are files and folders to check.
     *
     * @param string $arg The command line argument.
     * @param int    $pos The position of the argument on the command line.
     *
     * @return void
     */
    public function processUnknownArgument($arg, $pos)
    {
        // We don't know about any additional switches; just files.
        if ($arg[0] === '-') {
            if (!$this->dieOnUnknownArg) {
                return;
            }

            echo 'ERROR: option "'.$arg.'" not known.'.PHP_EOL.PHP_EOL;
            $this->printUsage();
            exit(2);
        }

        $file = PHP_CodeSniffer::realpath($arg);
        if (!file_exists($file)) {
            if (!$this->dieOnUnknownArg) {
                return;
            }

            echo 'ERROR: The file "'.$arg.'" does not exist.'.PHP_EOL.PHP_EOL;
            $this->printUsage();
            exit(2);
        } else {
            $this->values['files'][] = $file;
        }

    }//end processUnknownArgument()


    /**
     * Runs PHP_CodeSniffer over files and directories.
     *
     * @param array $values An array of values determined from CLI args.
     *
     * @return int The number of error and warning messages shown.
     * @see    getCommandLineValues()
     */
    public function process($values=array())
    {
        if (empty($values)) {
            $values = $this->getCommandLineValues();
        } else {
            $values       = array_merge($this->getDefaults(), $values);
            $this->values = $values;
        }

        if ($values['generator'] !== '') {
            $phpcs = new PHP_CodeSniffer($values['verbosity']);
            if ($values['standard'] === null) {
                $values['standard'] = $this->validateStandard(null);
            }

            foreach ($values['standard'] as $standard) {
                $phpcs->generateDocs(
                    $standard,
                    $values['sniffs'],
                    $values['generator']
                );
            }

            exit(0);
        }

        // If no standard is supplied, get the default.
        $values['standard'] = $this->validateStandard($values['standard']);
        foreach ($values['standard'] as $standard) {
            if (!PHP_CodeSniffer::isInstalledStandard($standard)) {
                // They didn't select a valid coding standard, so help them
                // out by letting them know which standards are installed.
                echo 'ERROR: the "'.$standard.'" coding standard is not installed. ';
                $this->printInstalledStandards();
                exit(2);
            }
        }

        if ($values['explain'] === true) {
            foreach ($values['standard'] as $standard) {
                $this->explainStandard($standard);
            }

            exit(0);
        }

        $phpcs = new PHP_CodeSniffer($values['verbosity'], null, null, null);
        $phpcs->setCli($this);
        $phpcs->initStandard($values['standard'], $values['sniffs'], $values['exclude']);
        $values = $this->values;

        $phpcs->setTabWidth($values['tabWidth']);
        $phpcs->setEncoding($values['encoding']);
        $phpcs->setInteractive($values['interactive']);

        // Set file extensions if they were specified. Otherwise,
        // let PHP_CodeSniffer decide on the defaults.
        if (!empty($values['extensions'])) {
            $phpcs->setAllowedFileExtensions($values['extensions']);
        }

        // Set ignore patterns if they were specified.
        if (!empty($values['ignored'])) {
            $ignorePatterns = array_merge($phpcs->getIgnorePatterns(), $values['ignored']);
            $phpcs->setIgnorePatterns($ignorePatterns);
        }

        // Set some convenience member vars.
        $this->errorSeverity = $values['errorSeverity'] === null ? PHPCS_DEFAULT_ERROR_SEV : $values['errorSeverity'];

        $this->warningSeverity = $values['warningSeverity'] === null ? PHPCS_DEFAULT_WARN_SEV : $values['warningSeverity'];

        if (empty($values['reports'])) {
            $values['reports']['full'] = $values['reportFile'];
            $this->values['reports']   = $values['reports'];
        }

        // Include bootstrap files.
        foreach ($values['bootstrap'] as $bootstrap) {
            include $bootstrap;
        }

        $phpcs->processFiles($values['files'], $values['local']);

        if (empty($values['files']) || $values['stdin'] !== null) {
            $fileContents = $values['stdin'];
            if ($fileContents === null) {
                // Check if they are passing in the file contents.
                $handle = fopen('php://stdin', 'r');
                stream_set_blocking($handle, true);
                $fileContents = stream_get_contents($handle);
                fclose($handle);
            }

            if ($fileContents === '') {
                // No files and no content passed in.
                echo 'ERROR: You must supply at least one file or directory to process.'.PHP_EOL.PHP_EOL;
                $this->printUsage();
                exit(2);
            } else {
                $this->values['stdin'] = $fileContents;
                $phpcs->processFile('STDIN', $fileContents);
            }
        }

        // Interactive runs don't require a final report and it doesn't really
        // matter what the retun value is because we know it isn't being read
        // by a script.
        if ($values['interactive'] === true) {
            return 0;
        }

        return $this->printErrorReport(
            $phpcs,
            $values['reports'],
            $values['showSources'],
            $values['reportFile'],
            $values['reportWidth']
        );

    }//end process()


    /**
     * Prints the error report for the run.
     *
     * Note that this function may actually print multiple reports
     * as the user may have specified a number of output formats.
     *
     * @param PHP_CodeSniffer $phpcs       The PHP_CodeSniffer object containing
     *                                     the errors.
     * @param array           $reports     A list of reports to print.
     * @param bool            $showSources TRUE if report should show error sources
     *                                     (not used by all reports).
     * @param string          $reportFile  A default file to log report output to.
     * @param int             $reportWidth How wide the screen reports should be.
     *
     * @return int The number of error and warning messages shown.
     */
    public function printErrorReport(
        PHP_CodeSniffer $phpcs,
        $reports,
        $showSources,
        $reportFile,
        $reportWidth
    ) {
        if (empty($reports)) {
            $reports['full'] = $reportFile;
        }

        $errors   = 0;
        $warnings = 0;
        $toScreen = false;

        foreach ($reports as $report => $output) {
            if ($output === null) {
                $output = $reportFile;
            }

            if ($reportFile === null) {
                $toScreen = true;
            }

            // We don't add errors here because the number of
            // errors reported by each report type will always be the
            // same, so we really just need 1 number.
            $result = $phpcs->reporting->printReport(
                $report,
                $showSources,
                $this->values,
                $output,
                $reportWidth
            );

            $errors   = $result['errors'];
            $warnings = $result['warnings'];
        }//end foreach

        // Only print timer output if no reports were
        // printed to the screen so we don't put additional output
        // in something like an XML report. If we are printing to screen,
        // the report types would have already worked out who should
        // print the timer info.
        if (PHP_CODESNIFFER_INTERACTIVE === false
            && (!$toScreen
            || (($errors + $warnings) === 0 && $this->values['showProgress'] === true))
        ) {
            PHP_CodeSniffer_Reporting::printRunTime();
        }

        // They should all return the same value, so it
        // doesn't matter which return value we end up using.
        $ignoreWarnings = PHP_CodeSniffer::getConfigData('ignore_warnings_on_exit');
        $ignoreErrors   = PHP_CodeSniffer::getConfigData('ignore_errors_on_exit');

        $return = ($errors + $warnings);
        if ($ignoreErrors !== null) {
            $ignoreErrors = (bool) $ignoreErrors;
            if ($ignoreErrors) {
                $return -= $errors;
            }
        }

        if ($ignoreWarnings !== null) {
            $ignoreWarnings = (bool) $ignoreWarnings;
            if ($ignoreWarnings) {
                $return -= $warnings;
            }
        }

        return $return;

    }//end printErrorReport()


    /**
     * Convert the passed standards into valid standards.
     *
     * Checks things like default values and case.
     *
     * @param array $standards The standards to validate.
     *
     * @return array
     */
    public function validateStandard($standards)
    {
        if ($standards === null) {
            // They did not supply a standard to use.
            // Look for a default ruleset in the current directory or higher.
            $currentDir = getcwd();

            do {
                $default = $currentDir.DIRECTORY_SEPARATOR.'phpcs.xml';
                if (is_file($default)) {
                    return array($default);
                }

                $default = $currentDir.DIRECTORY_SEPARATOR.'phpcs.xml.dist';
                if (is_file($default)) {
                    return array($default);
                }

                $lastDir    = $currentDir;
                $currentDir = dirname($currentDir);
            } while ($currentDir !== '.' && $currentDir !== $lastDir);

            // Try to get the default from the config system.
            $standard = PHP_CodeSniffer::getConfigData('default_standard');
            if ($standard === null) {
                // Product default standard.
                $standard = 'PEAR';
            }

            return explode(',', $standard);
        }//end if

        $cleaned   = array();
        $standards = (array) $standards;

        // Check if the standard name is valid, or if the case is invalid.
        $installedStandards = PHP_CodeSniffer::getInstalledStandards();
        foreach ($standards as $standard) {
            foreach ($installedStandards as $validStandard) {
                if (strtolower($standard) === strtolower($validStandard)) {
                    $standard = $validStandard;
                    break;
                }
            }

            $cleaned[] = $standard;
        }

        return $cleaned;

    }//end validateStandard()


    /**
     * Prints a report showing the sniffs contained in a standard.
     *
     * @param string $standard The standard to validate.
     *
     * @return void
     */
    public function explainStandard($standard)
    {
        $phpcs = new PHP_CodeSniffer();
        $phpcs->process(array(), $standard);
        $sniffs = $phpcs->getSniffs();
        $sniffs = array_keys($sniffs);
        sort($sniffs);

        ob_start();

        $lastStandard = '';
        $lastCount    = '';
        $sniffCount   = count($sniffs);
        $sniffs[]     = '___';

        echo PHP_EOL."The $standard standard contains $sniffCount sniffs".PHP_EOL;

        ob_start();

        foreach ($sniffs as $sniff) {
            $parts = explode('_', str_replace('\\', '_', $sniff));
            if ($lastStandard === '') {
                $lastStandard = $parts[0];
            }

            if ($parts[0] !== $lastStandard) {
                $sniffList = ob_get_contents();
                ob_end_clean();

                echo PHP_EOL.$lastStandard.' ('.$lastCount.' sniffs)'.PHP_EOL;
                echo str_repeat('-', (strlen($lastStandard.$lastCount) + 10));
                echo PHP_EOL;
                echo $sniffList;

                $lastStandard = $parts[0];
                $lastCount    = 0;

                ob_start();
            }

            echo '  '.$parts[0].'.'.$parts[2].'.'.substr($parts[3], 0, -5).PHP_EOL;
            $lastCount++;
        }//end foreach

        ob_end_clean();

    }//end explainStandard()


    /**
     * Prints out the gathered config data.
     *
     * @param array $data The config data to print.
     *
     * @return void
     */
    public function printConfigData($data)
    {
        $max  = 0;
        $keys = array_keys($data);
        foreach ($keys as $key) {
            $len = strlen($key);
            if (strlen($key) > $max) {
                $max = $len;
            }
        }

        if ($max === 0) {
            return;
        }

        $max += 2;
        ksort($data);
        foreach ($data as $name => $value) {
            echo str_pad($name.': ', $max).$value.PHP_EOL;
        }

    }//end printConfigData()


    /**
     * Prints out the usage information for this script.
     *
     * @return void
     */
    public function printUsage()
    {
        if (PHP_CODESNIFFER_CBF === true) {
            $this->printPHPCBFUsage();
        } else {
            $this->printPHPCSUsage();
        }

    }//end printUsage()


    /**
     * Prints out the usage information for PHPCS.
     *
     * @return void
     */
    public function printPHPCSUsage()
    {
        echo 'Usage: phpcs [-nwlsaepqvi] [-d key[=value]] [--colors] [--no-colors] [--stdin-path=<stdinPath>]'.PHP_EOL;
        echo '    [--report=<report>] [--report-file=<reportFile>] [--report-<report>=<reportFile>] ...'.PHP_EOL;
        echo '    [--report-width=<reportWidth>] [--generator=<generator>] [--tab-width=<tabWidth>]'.PHP_EOL;
        echo '    [--severity=<severity>] [--error-severity=<severity>] [--warning-severity=<severity>]'.PHP_EOL;
        echo '    [--runtime-set key value] [--config-set key value] [--config-delete key] [--config-show]'.PHP_EOL;
        echo '    [--standard=<standard>] [--sniffs=<sniffs>] [--exclude=<sniffs>] [--encoding=<encoding>]'.PHP_EOL;
        echo '    [--extensions=<extensions>] [--ignore=<patterns>] [--bootstrap=<bootstrap>]'.PHP_EOL;
        echo '    [--file-list=<fileList>] <file> ...'.PHP_EOL;
        echo '                      Set runtime value (see --config-set) '.PHP_EOL;
        echo '        -n            Do not print warnings (shortcut for --warning-severity=0)'.PHP_EOL;
        echo '        -w            Print both warnings and errors (this is the default)'.PHP_EOL;
        echo '        -l            Local directory only, no recursion'.PHP_EOL;
        echo '        -s            Show sniff codes in all reports'.PHP_EOL;
        echo '        -a            Run interactively'.PHP_EOL;
        echo '        -e            Explain a standard by showing the sniffs it includes'.PHP_EOL;
        echo '        -p            Show progress of the run'.PHP_EOL;
        echo '        -q            Quiet mode; disables progress and verbose output'.PHP_EOL;
        echo '        -v[v][v]      Print verbose output'.PHP_EOL;
        echo '        -i            Show a list of installed coding standards'.PHP_EOL;
        echo '        -d            Set the [key] php.ini value to [value] or [true] if value is omitted'.PHP_EOL;
        echo '        --help        Print this help message'.PHP_EOL;
        echo '        --version     Print version information'.PHP_EOL;
        echo '        --colors      Use colors in output'.PHP_EOL;
        echo '        --no-colors   Do not use colors in output (this is the default)'.PHP_EOL;
        echo '        <file>        One or more files and/or directories to check'.PHP_EOL;
        echo '        <fileList>    A file containing a list of files and/or directories to check (one per line)'.PHP_EOL;
        echo '        <stdinPath>   If processing STDIN, the file path that STDIN will be processed as '.PHP_EOL;
        echo '        <bootstrap>   A comma separated list of files to run before processing starts'.PHP_EOL;
        echo '        <encoding>    The encoding of the files being checked (default is iso-8859-1)'.PHP_EOL;
        echo '        <extensions>  A comma separated list of file extensions to check'.PHP_EOL;
        echo '                      (extension filtering only valid when checking a directory)'.PHP_EOL;
        echo '                      The type of the file can be specified using: ext/type'.PHP_EOL;
        echo '                      e.g., module/php,es/js'.PHP_EOL;
        echo '        <generator>   Uses either the "HTML", "Markdown" or "Text" generator'.PHP_EOL;
        echo '                      (forces documentation generation instead of checking)'.PHP_EOL;
        echo '        <patterns>    A comma separated list of patterns to ignore files and directories'.PHP_EOL;
        echo '        <report>      Print either the "full", "xml", "checkstyle", "csv"'.PHP_EOL;
        echo '                      "json", "emacs", "source", "summary", "diff", "junit"'.PHP_EOL;
        echo '                      "svnblame", "gitblame", "hgblame" or "notifysend" report'.PHP_EOL;
        echo '                      (the "full" report is printed by default)'.PHP_EOL;
        echo '        <reportFile>  Write the report to the specified file path'.PHP_EOL;
        echo '        <reportWidth> How many columns wide screen reports should be printed'.PHP_EOL;
        echo '                      or set to "auto" to use current screen width, where supported'.PHP_EOL;
        echo '        <sniffs>      A comma separated list of sniff codes to include or exclude during checking'.PHP_EOL;
        echo '                      (all sniffs must be part of the specified standard)'.PHP_EOL;
        echo '        <severity>    The minimum severity required to display an error or warning'.PHP_EOL;
        echo '        <standard>    The name or path of the coding standard to use'.PHP_EOL;
        echo '        <tabWidth>    The number of spaces each tab represents'.PHP_EOL;

    }//end printPHPCSUsage()


    /**
     * Prints out the usage information for PHPCBF.
     *
     * @return void
     */
    public function printPHPCBFUsage()
    {
        echo 'Usage: phpcbf [-nwli] [-d key[=value]] [--stdin-path=<stdinPath>]'.PHP_EOL;
        echo '    [--standard=<standard>] [--sniffs=<sniffs>] [--exclude=<sniffs>] [--suffix=<suffix>]'.PHP_EOL;
        echo '    [--severity=<severity>] [--error-severity=<severity>] [--warning-severity=<severity>]'.PHP_EOL;
        echo '    [--tab-width=<tabWidth>] [--encoding=<encoding>]'.PHP_EOL;
        echo '    [--extensions=<extensions>] [--ignore=<patterns>] [--bootstrap=<bootstrap>]'.PHP_EOL;
        echo '    [--file-list=<fileList>] <file> ...'.PHP_EOL;
        echo '        -n            Do not fix warnings (shortcut for --warning-severity=0)'.PHP_EOL;
        echo '        -w            Fix both warnings and errors (on by default)'.PHP_EOL;
        echo '        -l            Local directory only, no recursion'.PHP_EOL;
        echo '        -i            Show a list of installed coding standards'.PHP_EOL;
        echo '        -d            Set the [key] php.ini value to [value] or [true] if value is omitted'.PHP_EOL;
        echo '        --help        Print this help message'.PHP_EOL;
        echo '        --version     Print version information'.PHP_EOL;
        echo '        --no-patch    Do not make use of the "diff" or "patch" programs'.PHP_EOL;
        echo '        <file>        One or more files and/or directories to fix'.PHP_EOL;
        echo '        <fileList>    A file containing a list of files and/or directories to fix (one per line)'.PHP_EOL;
        echo '        <stdinPath>   If processing STDIN, the file path that STDIN will be processed as '.PHP_EOL;
        echo '        <bootstrap>   A comma separated list of files to run before processing starts'.PHP_EOL;
        echo '        <encoding>    The encoding of the files being fixed (default is iso-8859-1)'.PHP_EOL;
        echo '        <extensions>  A comma separated list of file extensions to fix'.PHP_EOL;
        echo '                      (extension filtering only valid when checking a directory)'.PHP_EOL;
        echo '                      The type of the file can be specified using: ext/type'.PHP_EOL;
        echo '                      e.g., module/php,es/js'.PHP_EOL;
        echo '        <patterns>    A comma separated list of patterns to ignore files and directories'.PHP_EOL;
        echo '        <sniffs>      A comma separated list of sniff codes to include or exclude during fixing'.PHP_EOL;
        echo '                      (all sniffs must be part of the specified standard)'.PHP_EOL;
        echo '        <severity>    The minimum severity required to fix an error or warning'.PHP_EOL;
        echo '        <standard>    The name or path of the coding standard to use'.PHP_EOL;
        echo '        <suffix>      Write modified files to a filename using this suffix'.PHP_EOL;
        echo '                      ("diff" and "patch" are not used in this mode)'.PHP_EOL;
        echo '        <tabWidth>    The number of spaces each tab represents'.PHP_EOL;

    }//end printPHPCBFUsage()


    /**
     * Prints out a list of installed coding standards.
     *
     * @return void
     */
    public function printInstalledStandards()
    {
        $installedStandards = PHP_CodeSniffer::getInstalledStandards();
        $numStandards       = count($installedStandards);

        if ($numStandards === 0) {
            echo 'No coding standards are installed.'.PHP_EOL;
        } else {
            $lastStandard = array_pop($installedStandards);
            if ($numStandards === 1) {
                echo "The only coding standard installed is $lastStandard".PHP_EOL;
            } else {
                $standardList  = implode(', ', $installedStandards);
                $standardList .= ' and '.$lastStandard;
                echo 'The installed coding standards are '.$standardList.PHP_EOL;
            }
        }

    }//end printInstalledStandards()


    /**
     * Set report width based on terminal width.
     *
     * @param int $width The width of the report. If "auto" then will
     *                   be replaced by the terminal width.
     *
     * @return int
     */
    private function _validateReportWidth($width)
    {
        if ($width === 'auto'
            && preg_match('|\d+ (\d+)|', shell_exec('stty size 2>&1'), $matches) === 1
        ) {
            return (int) $matches[1];
        }

        return (int) $width;

    }//end _validateReportWidth()


}//end class
