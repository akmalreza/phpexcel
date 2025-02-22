<?php
/**
 * Mercurial report for PHP_CodeSniffer.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Ben Selby <benmatselby@gmail.com>
 * @copyright 2009-2014 SQLI <www.sqli.com>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * Mercurial report for PHP_CodeSniffer.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Ben Selby <benmatselby@gmail.com>
 * @copyright 2009-2014 SQLI <www.sqli.com>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PHP_CodeSniffer_Reports_Hgblame extends PHP_CodeSniffer_Reports_VersionControl
{

    /**
     * The name of the report we want in the output
     *
     * @var string
     */
    protected $reportName = 'MERCURIAL';


    /**
     * Extract the author from a blame line.
     *
     * @param string $line Line to parse.
     *
     * @return mixed string or false if impossible to recover.
     */
    protected function getAuthor($line)
    {
        $blameParts = array();
        $line       = preg_replace('|\s+|', ' ', $line);

        preg_match(
            '|(.+\d{2}:\d{2}:\d{2}\s\d{4}\s.\d{4}:)|',
            $line,
            $blameParts
        );

        if (!isset($blameParts[0])) {
            return false;
        }

        $parts = explode(' ', $blameParts[0]);

        if (count($parts) < 6) {
            return false;
        }

        $parts = array_slice($parts, 0, (count($parts) - 6));

        return trim(preg_replace('|<.+>|', '', implode($parts, ' ')));

    }//end getAuthor()


    /**
     * Gets the blame output.
     *
     * @param string $filename File to blame.
     *
     * @return array
     */
    protected function getBlameContent($filename)
    {
        $cwd = getcwd();

        $fileParts = explode(DIRECTORY_SEPARATOR, $filename);
        $found     = false;
        $location  = '';
        while (!empty($fileParts)) {
            array_pop($fileParts);
            $location = implode($fileParts, DIRECTORY_SEPARATOR);
            if (is_dir($location.DIRECTORY_SEPARATOR.'.hg')) {
                $found = true;
                break;
            }
        }

        if ($found) {
            chdir($location);
        } else {
            echo 'ERROR: Could not locate .hg directory '.PHP_EOL.PHP_EOL;
            exit(2);
        }

        $command = 'hg blame -u -d -v "'.$filename.'" 2>&1';
        $handle  = popen($command, 'r');
        if ($handle === false) {
            echo 'ERROR: Could not execute "'.$command.'"'.PHP_EOL.PHP_EOL;
            exit(2);
        }

        $rawContent = stream_get_contents($handle);
        fclose($handle);

        $blames = explode("\n", $rawContent);
        chdir($cwd);

        return $blames;

    }//end getBlameContent()


}//end class
