<?php
/**
 * PSR1_Sniffs_Methods_CamelCapsMethodNameSniff.
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

if (!class_exists('Generic_Sniffs_NamingConventions_CamelCapsFunctionNameSniff', true)) {
    throw new PHP_CodeSniffer_Exception('Class Generic_Sniffs_NamingConventions_CamelCapsFunctionNameSniff not found');
}

/**
 * PSR1_Sniffs_Methods_CamelCapsMethodNameSniff.
 *
 * Ensures method names are defined using camel case.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PSR1_Sniffs_Methods_CamelCapsMethodNameSniff extends Generic_Sniffs_NamingConventions_CamelCapsFunctionNameSniff
{


    /**
     * Processes the tokens within the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     * @param int                  $currScope The position of the current scope.
     *
     * @return void
     */
    protected function processTokenWithinScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $currScope)
    {
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            // Ignore closures.
            return;
        }

        // Ignore magic methods.
        if (preg_match('|^__[^_]|', $methodName) !== 0) {
            $magicPart = strtolower(substr($methodName, 2));
            if (isset($this->magicMethods[$magicPart])
                || isset($this->methodsDoubleUnderscore[$magicPart])
            ) {
                return;
            }
        }

        $testName = ltrim($methodName, '_');
        if ($testName !== '' && !PHP_CodeSniffer::isCamelCaps($testName, false, true, false)) {
            $error     = 'Method name "%s" is not in camel caps format';
            $className = $phpcsFile->getDeclarationName($currScope);
            $errorData = array($className.'::'.$methodName);
            $phpcsFile->addError($error, $stackPtr, 'NotCamelCaps', $errorData);
            $phpcsFile->recordMetric($stackPtr, 'CamelCase method name', 'no');
        } else {
            $phpcsFile->recordMetric($stackPtr, 'CamelCase method name', 'yes');
        }

    }//end processTokenWithinScope()


    /**
     * Processes the tokens outside the scope.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being processed.
     * @param int                  $stackPtr  The position where this token was
     *                                        found.
     *
     * @return void
     */
    protected function processTokenOutsideScope(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {

    }//end processTokenOutsideScope()


}//end class
