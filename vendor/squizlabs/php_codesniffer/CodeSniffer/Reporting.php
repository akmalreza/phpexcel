<?php

/**
 * A class to manage reporting.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Gabriele Santini <gsantini@sqli.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2009-2014 SQLI <www.sqli.com>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
/**
 * A class to manage reporting.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Gabriele Santini <gsantini@sqli.com>
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2009-2014 SQLI <www.sqli.com>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PHP_CodeSniffer_Reporting
{

    /**
     * Total number of files that contain errors or warnings.
     */
    public int $totalFiles = 0;

    /**
     * Total number of errors found during the run.
     */
    public int $totalErrors = 0;

    /**
     * Total number of warnings found during the run.
     */
    public int $totalWarnings = 0;

    /**
     * Total number of errors/warnings that can be fixed.
     */
    public int $totalFixable = 0;

    /**
     * When the PHPCS run started.
     *
     * @var float
     */
    public static $startTime = 0;

    /**
     * A list of reports that have written partial report output.
     */
    private array $_cachedReports = array();

    /**
     * A cache of report objects.
     */
    private array $_reports = array();

    /**
     * A cache of opened tmp files.
     */
    private array $_tmpFiles = array();


    /**
     * Produce the appropriate report object based on $type parameter.
     *
     * @param string $type The type of the report.
     *
     * @return PHP_CodeSniffer_Report
     * @throws PHP_CodeSniffer_Exception If report is not available.
     */
    public function factory($type)
    {
        $type = ucfirst($type);
        if (isset($this->_reports[$type])) {
            return $this->_reports[$type];
        }

        if (strpos($type, '.') !== false) {
            // This is a path to a custom report class.
            $filename = realpath($type);
            if ($filename === false) {
                echo 'ERROR: Custom report "'.$type.'" not found'.PHP_EOL;
                exit(2);
            }

            $reportClassName = 'PHP_CodeSniffer_Reports_'.basename($filename);
            $reportClassName = substr($reportClassName, 0, strpos($reportClassName, '.'));
            include_once $filename;
        } else {
            $filename        = $type.'.php';
            $reportClassName = 'PHP_CodeSniffer_Reports_'.$type;
            if (!class_exists($reportClassName, true)) {
                echo 'ERROR: Report type "'.$type.'" not found'.PHP_EOL;
                exit(2);
            }
        }//end if

        $reportClass = new $reportClassName();
        if (!$reportClass instanceof PHP_CodeSniffer_Report) {
            throw new PHP_CodeSniffer_Exception('Class "'.$reportClassName.'" must implement the "PHP_CodeSniffer_Report" interface.');
        }

        $this->_reports[$type] = $reportClass;
        return $this->_reports[$type];

    }//end factory()


    /**
     * Actually generates the report.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file that has been processed.
     * @param array                $cliValues An array of command line arguments.
     *
     * @return void
     */
    public function cacheFileReport(PHP_CodeSniffer_File $phpcsFile, array $cliValues)
    {
        if (!isset($cliValues['reports'])) {
            // This happens during unit testing, or any time someone just wants
            // the error data and not the printed report.
            return;
        }

        $reportData  = $this->prepareFileReport($phpcsFile);
        $errorsShown = false;

        foreach ($cliValues['reports'] as $report => $output) {
            $reportClass = $this->factory($report);
            $report      = get_class($reportClass);

            ob_start();
            $result = $reportClass->generateFileReport($reportData, $phpcsFile, $cliValues['showSources'], $cliValues['reportWidth']);
            if ($result) {
                $errorsShown = true;
            }

            $generatedReport = ob_get_contents();
            ob_end_clean();

            if ($output === null && $cliValues['reportFile'] !== null) {
                $output = $cliValues['reportFile'];
            }

            if ($output === null) {
                // Using a temp file.
                if (!isset($this->_tmpFiles[$report])) {
                    if (function_exists('sys_get_temp_dir')) {
                        // This is needed for HHVM support, but only available from 5.2.1.
                        $this->_tmpFiles[$report] = fopen(tempnam(sys_get_temp_dir(), 'phpcs'), 'w');
                    } else {
                        $this->_tmpFiles[$report] = tmpfile();
                    }
                }

                fwrite($this->_tmpFiles[$report], $generatedReport);
            } else {
                $flags = FILE_APPEND;
                if (!isset($this->_cachedReports[$report])) {
                    $this->_cachedReports[$report] = true;
                    $flags = null;
                }

                file_put_contents($output, $generatedReport, $flags);
            }//end if
        }//end foreach

        if ($errorsShown) {
            $this->totalFiles++;
            $this->totalErrors   += $reportData['errors'];
            $this->totalWarnings += $reportData['warnings'];
            $this->totalFixable  += $reportData['fixable'];
        }

    }//end cacheFileReport()


    /**
     * Generates and prints a final report.
     *
     * Returns an array with the number of errors and the number of
     * warnings, in the form ['errors' => int, 'warnings' => int].
     *
     * @param string  $report      Report type.
     * @param boolean $showSources Show sources?
     * @param array   $cliValues   An array of command line arguments.
     * @param string  $reportFile  Report file to generate.
     * @param integer $reportWidth Report max width.
     *
     * @return int[]
     */
    public function printReport(
        $report,
        $showSources,
        array $cliValues,
        $reportFile='',
        $reportWidth=80
    ) {
        $reportClass = $this->factory($report);
        $report      = get_class($reportClass);

        if ($reportFile !== null) {
            $filename = $reportFile;
            $toScreen = false;

            $reportCache = file_exists($filename)
                && isset($this->_cachedReports[$report]) ? file_get_contents($filename) : '';
        } else {
            if (isset($this->_tmpFiles[$report])) {
                $data        = stream_get_meta_data($this->_tmpFiles[$report]);
                $filename    = $data['uri'];
                $reportCache = file_get_contents($filename);
                fclose($this->_tmpFiles[$report]);
            } else {
                $reportCache = '';
                $filename    = null;
            }

            $toScreen = true;
        }//end if

        ob_start();
        $reportClass->generate(
            $reportCache,
            $this->totalFiles,
            $this->totalErrors,
            $this->totalWarnings,
            $this->totalFixable,
            $showSources,
            $reportWidth,
            $toScreen
        );
        $generatedReport = ob_get_contents();
        ob_end_clean();

        if ($cliValues['colors'] !== true || $reportFile !== null) {
            $generatedReport = preg_replace('`\033\[\d+m`', '', $generatedReport);
        }

        if ($reportFile !== null) {
            if (PHP_CODESNIFFER_VERBOSITY > 0) {
                echo $generatedReport;
            }

            file_put_contents($reportFile, $generatedReport.PHP_EOL);
        } else {
            echo $generatedReport;
            if ($filename !== null && file_exists($filename)) {
                unlink($filename);
            }
        }

        return array(
                'errors'   => $this->totalErrors,
                'warnings' => $this->totalWarnings,
               );

    }//end printReport()


    /**
     * Pre-process and package violations for all files.
     *
     * Used by error reports to get a packaged list of all errors in each file.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file that has been processed.
     *
     * @return array
     */
    public function prepareFileReport(PHP_CodeSniffer_File $phpcsFile)
    {
        $report = array(
                   'filename' => $phpcsFile->getFilename(),
                   'errors'   => $phpcsFile->getErrorCount(),
                   'warnings' => $phpcsFile->getWarningCount(),
                   'fixable'  => $phpcsFile->getFixableCount(),
                   'messages' => array(),
                  );

        if ($report['errors'] === 0 && $report['warnings'] === 0) {
            // Prefect score!
            return $report;
        }

        $errors = array();

        // Merge errors and warnings.
        foreach ($phpcsFile->getErrors() as $line => $lineErrors) {
            if (!is_array($lineErrors)) {
                continue;
            }

            foreach ($lineErrors as $column => $colErrors) {
                $newErrors = array();
                foreach ($colErrors as $data) {
                    $newErrors[] = array(
                                    'message'  => $data['message'],
                                    'source'   => $data['source'],
                                    'severity' => $data['severity'],
                                    'fixable'  => $data['fixable'],
                                    'type'     => 'ERROR',
                                   );
                }//end foreach

                $errors[$line][$column] = $newErrors;
            }//end foreach

            ksort($errors[$line]);
        }//end foreach

        foreach ($phpcsFile->getWarnings() as $line => $lineWarnings) {
            if (!is_array($lineWarnings)) {
                continue;
            }

            foreach ($lineWarnings as $column => $colWarnings) {
                $newWarnings = array();
                foreach ($colWarnings as $data) {
                    $newWarnings[] = array(
                                      'message'  => $data['message'],
                                      'source'   => $data['source'],
                                      'severity' => $data['severity'],
                                      'fixable'  => $data['fixable'],
                                      'type'     => 'WARNING',
                                     );
                }//end foreach

                if (!isset($errors[$line])) {
                    $errors[$line] = array();
                }

                if (isset($errors[$line][$column])) {
                    $errors[$line][$column] = array_merge(
                        $newWarnings,
                        $errors[$line][$column]
                    );
                } else {
                    $errors[$line][$column] = $newWarnings;
                }
            }//end foreach

            ksort($errors[$line]);
        }//end foreach

        ksort($errors);
        $report['messages'] = $errors;
        return $report;

    }//end prepareFileReport()


    /**
     * Start recording time for the run.
     *
     * @return void
     */
    public static function startTiming()
    {

        self::$startTime = microtime(true);

    }//end startTiming()


    /**
     * Print information about the run.
     *
     * @return void
     */
    public static function printRunTime()
    {
        $time = ((microtime(true) - self::$startTime) * 1000);

        if ($time > 60000) {
            $mins = floor($time / 60000);
            $secs = round((($time % 60000) / 1000), 2);
            $time = $mins.' mins';
            if ($secs !== 0) {
                $time .= ", $secs secs";
            }
        } elseif ($time > 1000) {
            $time = round(($time / 1000), 2).' secs';
        } else {
            $time = round($time).'ms';
        }

        $mem = round((memory_get_peak_usage(true) / (1024 * 1024)), 2).'Mb';
        echo "Time: $time; Memory: $mem".PHP_EOL.PHP_EOL;

    }//end printRunTime()


}
//end class
