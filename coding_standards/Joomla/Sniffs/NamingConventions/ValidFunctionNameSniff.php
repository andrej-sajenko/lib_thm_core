<?php
/**
 * Joomla_Sniffs_NamingConventions_ValidFunctionNameSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id: ValidFunctionNameSniff.php 292390 2009-12-21 00:32:14Z squiz $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PHP_CodeSniffer_Standards_AbstractScopeSniff', true) === false)
{
	throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_Standards_AbstractScopeSniff not found');
}

/**
 * Joomla_Sniffs_NamingConventions_ValidFunctionNameSniff.
 *
 * Ensures method names are correct depending on whether they are public
 * or private, and that functions are named correctly.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   Release: 1.3.0RC2
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class Joomla_Sniffs_NamingConventions_ValidFunctionNameSniff extends PHP_CodeSniffer_Standards_AbstractScopeSniff
{

	/**
	 * A list of all PHP magic methods.
	 *
	 * @var array
	 */
	protected $magicMethods = array(
		'construct',
		'destruct',
		'call',
		'callStatic',
		'get',
		'set',
		'isset',
		'unset',
		'sleep',
		'wakeup',
		'toString',
		'set_state',
		'clone',
		'invoke',
	);

	/**
	 * A list of all PHP magic functions.
	 *
	 * @var array
	 */
	protected $magicFunctions = array('autoload');


	/**
	 * Constructs a Joomla_Sniffs_NamingConventions_ValidFunctionNameSniff.
	 */
	public function __construct()
	{
		parent::__construct(array(T_CLASS, T_INTERFACE), array(T_FUNCTION), true);

	}//end __construct()

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
		if ($methodName === null)
		{
			// Ignore closures.
			return;
		}

		$className = $phpcsFile->getDeclarationName($currScope);
		$errorData = array($className . '::' . $methodName);

		// Is this a magic method. IE. is prefixed with "__".
		if (preg_match('|^__|', $methodName) !== 0)
		{
			$magicPart = substr($methodName, 2);
			if (in_array($magicPart, $this->magicMethods) === false)
			{
				$error = 'Method name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';

				$phpcsFile->addError($error, $stackPtr, 'MethodDoubleUnderscore', $errorData);
			}

			return;
		}

		// PHP4 constructors are allowed to break our rules.
		if ($methodName === $className)
		{
			return;
		}

		// PHP4 destructors are allowed to break our rules.
		if ($methodName === '_' . $className)
		{
			return;
		}

		$methodProps    = $phpcsFile->getMethodProperties($stackPtr);
		$isPublic       = ($methodProps['scope'] === 'private') ? false : true;
		$scope          = $methodProps['scope'];
		$scopeSpecified = $methodProps['scope_specified'];

		// Detect if it is marked deprecated
		$find       = array(
			T_COMMENT,
			T_DOC_COMMENT,
			T_CLASS,
			T_FUNCTION,
			T_OPEN_TAG,
		);
		$tokens     = $phpcsFile->getTokens();
		$commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1));
		if ($commentEnd !== false && $tokens[$commentEnd]['code'] === T_DOC_COMMENT)
		{
			$commentStart = $phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1;
			$comment      = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

			try
			{
				$this->commentParser = new PHP_CodeSniffer_CommentParser_FunctionCommentParser($comment, $phpcsFile);
				$this->commentParser->parse();
			}
			catch (PHP_CodeSniffer_CommentParser_ParserException $e)
			{
				$line = ($e->getLineWithinComment() + $commentStart);
				$phpcsFile->addError($e->getMessage(), $line, 'FailedParse');

				return;
			}

			$deprecated = $this->commentParser->getDeprecated();

			return !is_null($deprecated);
		}
		else
		{
			return false;
		}

		// Methods must not have an underscore on the front.
		if ($isDeprecated === false && $scopeSpecified === true && $methodName{0} === '_')
		{
			$error = '%s method name "%s" must not be prefixed with an underscore';
			$data  = array(
				ucfirst($scope),
				$errorData[0],
			);
			// AJE Changed from error to warning.
			$phpcsFile->addWarning($error, $stackPtr, 'PublicUnderscore', $data);

			return;
		}

		// If the scope was specified on the method, then the method must be
		// camel caps and an underscore should be checked for. If it wasn't
		// specified, treat it like a public method and remove the underscore
		// prefix if there is one because we cant determine if it is private or
		// public.
		$testMethodName = $methodName;
		if ($scopeSpecified === false && $methodName{0} === '_')
		{
			$testMethodName = substr($methodName, 1);
		}

		if ($isDeprecated === false && PHP_CodeSniffer::isCamelCaps($testMethodName, false, $isPublic, false) === false)
		{
			if ($scopeSpecified === true)
			{
				$error = '%s method name "%s" is not in camel caps format';
				$data  = array(
					ucfirst($scope),
					$errorData[0],
				);
				// AJE Change to warning.
				$phpcsFile->addWarning($error, $stackPtr, 'ScopeNotCamelCaps', $data);
			}
			else
			{
				$error = 'Method name "%s" is not in camel caps format';
				// AJE Change to warning.
				$phpcsFile->addWarning($error, $stackPtr, 'NotCamelCaps', $errorData);
			}

			return;
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
		$functionName = $phpcsFile->getDeclarationName($stackPtr);
		if ($functionName === null)
		{
			// Ignore closures.
			return;
		}

		$errorData = array($functionName);

		// Is this a magic function. IE. is prefixed with "__".
		if (preg_match('|^__|', $functionName) !== 0)
		{
			$magicPart = substr($functionName, 2);
			if (in_array($magicPart, $this->magicFunctions) === false)
			{
				$error = 'Function name "%s" is invalid; only PHP magic methods should be prefixed with a double underscore';
				$phpcsFile->addError($error, $stackPtr, 'FunctionDoubleUnderscore', $errorData);
			}

			return;
		}

		// Function names can be in two parts; the package name and
		// the function name.
		$packagePart   = '';
		$camelCapsPart = '';
		$underscorePos = strrpos($functionName, '_');
		if ($underscorePos === false)
		{
			$camelCapsPart = $functionName;
		}
		else
		{
			$packagePart   = substr($functionName, 0, $underscorePos);
			$camelCapsPart = substr($functionName, ($underscorePos + 1));

			// We don't care about _'s on the front.
			$packagePart = ltrim($packagePart, '_');
		}

		// If it has a package part, make sure the first letter is a capital.
		if ($packagePart !== '')
		{
			if ($functionName{0} === '_')
			{
				$error = 'Function name "%s" is invalid; methods should not be prefixed with an underscore';
				$phpcsFile->addError($error, $stackPtr, 'FunctionUnderscore', $errorData);

				return;
			}

			if ($functionName{0} !== strtoupper($functionName{0}))
			{
				$error = 'Function name "%s" is prefixed with a package name but does not begin with a capital letter';
				$phpcsFile->addError($error, $stackPtr, 'FunctionNoCaptial', $errorData);

				return;
			}
		}

		// If it doesn't have a camel caps part, it's not valid.
		if (trim($camelCapsPart) === '')
		{
			$error = 'Function name "%s" is not valid; name appears incomplete';
			$phpcsFile->addError($error, $stackPtr, 'FunctionInvalid', $errorData);

			return;
		}

		$validName        = true;
		$newPackagePart   = $packagePart;
		$newCamelCapsPart = $camelCapsPart;

		// Every function must have a camel caps part, so check that first.
		if (PHP_CodeSniffer::isCamelCaps($camelCapsPart, false, true, false) === false)
		{
			$validName        = false;
			$newCamelCapsPart = strtolower($camelCapsPart{0}) . substr($camelCapsPart, 1);
		}

		if ($packagePart !== '')
		{
			// Check that each new word starts with a capital.
			$nameBits = explode('_', $packagePart);
			foreach ($nameBits as $bit)
			{
				if ($bit{0} !== strtoupper($bit{0}))
				{
					$newPackagePart = '';
					foreach ($nameBits as $bit)
					{
						$newPackagePart .= strtoupper($bit{0}) . substr($bit, 1) . '_';
					}

					$validName = false;
					break;
				}
			}
		}

		if ($validName === false)
		{
			$newName = rtrim($newPackagePart, '_') . '_' . $newCamelCapsPart;
			if ($newPackagePart === '')
			{
				$newName = $newCamelCapsPart;
			}
			else
			{
				$newName = rtrim($newPackagePart, '_') . '_' . $newCamelCapsPart;
			}

			$error  = 'Function name "%s" is invalid; consider "%s" instead';
			$data   = $errorData;
			$data[] = $newName;
			$phpcsFile->addError($error, $stackPtr, 'FunctionNameInvalid', $data);
		}

	}//end processTokenOutsideScope()


}//end class

?>
