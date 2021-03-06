<?php
/**
 * Parses and verifies the doc comments for files.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @author    Niklas Simonis <niklas.simonis@mni.thm.de>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id: FileCommentSniff.php 301632 2010-07-28 01:57:56Z squiz $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PHP_CodeSniffer_CommentParser_ClassCommentParser', true) === false)
{
	throw new PHP_CodeSniffer_Exception('Class PHP_CodeSniffer_CommentParser_ClassCommentParser not found');
}

/**
 * Parses and verifies the doc comments for files.
 *
 * Verifies that :
 * <ul>
 *  <li>A doc comment exists.</li>
 *  <li>There is a blank newline after the short description.</li>
 *  <li>There is a blank newline between the long and short description.</li>
 *  <li>There is a blank newline between the long description and tags.</li>
 *  <li>A PHP version is specified.</li>
 *  <li>Check the order of the tags.</li>
 *  <li>Check the indentation of each tag.</li>
 *  <li>Check required and optional tags and the format of their content.</li>
 * </ul>
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
class THMJoomla_Sniffs_Commenting_FileCommentSniff implements PHP_CodeSniffer_Sniff
{

	/**
	 * The header comment parser for the current file.
	 *
	 * @var PHP_CodeSniffer_Comment_Parser_ClassCommentParser
	 */
	protected $commentParser = null;

	/**
	 * The header comment parser for the current file.
	 *
	 * @var PHP_CodeSniffer_Comment_Parser_ $commentParserLink
	 */
	protected $commentParserLink = null;

	/**
	 * The current PHP_CodeSniffer_File object we are processing.
	 *
	 * @var PHP_CodeSniffer_File
	 */
	protected $currentFile = null;

	/**
	 * Tags in correct order and related info.
	 *
	 * @var array
	 */
	protected $tags = array(
		'version'    => array(
			'required'       => false,
			'allow_multiple' => false,
			'order_text'     => 'must be first',
		),
		'category'   => array(
			'required'       => true,
			'allow_multiple' => false,
			'order_text'     => 'precedes @package',
		),
		'package'    => array(
			'required'       => true,
			'allow_multiple' => false,
			'order_text'     => 'must follows @category (if used)',
		),
		'subpackage' => array(
			'required'       => false,
			'allow_multiple' => false,
			'order_text'     => 'must follow @package',
		),
		'author'     => array(
			'required'       => true,
			'allow_multiple' => true,
			'order_text'     => 'must follow @subpackage (if used) or @package',
		),
		'copyright'  => array(
			'required'       => true,
			'allow_multiple' => true,
			'order_text'     => 'must follow @author (if used), @subpackage (if used) or @package',
		),
		'license'    => array(
			'required'       => true,
			'allow_multiple' => false,
			'order_text'     => 'must follow @copyright',
		),
		'link'       => array(
			'required'       => true,
			'allow_multiple' => true,
			'order_text'     => 'must follow @license',
		),
		'see'        => array(
			'required'       => false,
			'allow_multiple' => true,
			'order_text'     => 'must follow @link (if used) or @license',
		),
		'since'      => array(
			'required'       => false,
			'allow_multiple' => false,
			'order_text'     => 'must follows @see (if used), @link (if used) or @license',
		),
		'deprecated' => array(
			'required'       => false,
			'allow_multiple' => false,
			'order_text'     => 'must follow @since (if used), @see (if used), @link (if used) or @license',
		),
	);


	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(T_OPEN_TAG);

	}//end register()


	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$this->currentFile = $phpcsFile;

		// We are only interested if this is the first open tag.
		if ($stackPtr !== 0)
		{
			if ($phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) !== false)
			{
				return;
			}
		}

		$tokens = $phpcsFile->getTokens();

		// Find the next non whitespace token.
		$commentStart
			= $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

		// Allow declare() statements at the top of the file.
		if ($tokens[$commentStart]['code'] === T_DECLARE)
		{
			$semicolon = $phpcsFile->findNext(T_SEMICOLON, ($commentStart + 1));
			$commentStart
			           = $phpcsFile->findNext(T_WHITESPACE, ($semicolon + 1), null, true);
		}

		// Ignore vim header.
		if ($tokens[$commentStart]['code'] === T_COMMENT)
		{
			if (strstr($tokens[$commentStart]['content'], 'vim:') !== false)
			{
				$commentStart = $phpcsFile->findNext(
					T_WHITESPACE,
					($commentStart + 1),
					null,
					true
				);
			}
		}

		$errorToken = ($stackPtr + 1);
		if (isset($tokens[$errorToken]) === false)
		{
			$errorToken--;
		}

		if ($tokens[$commentStart]['code'] === T_CLOSE_TAG)
		{
			// We are only interested if this is the first open tag.
			return;
		}
		else
		{
			if ($tokens[$commentStart]['code'] === T_COMMENT)
			{
				$error = 'You must use "/**" style comments for a file comment';
				$phpcsFile->addError($error, $errorToken, 'WrongStyle');

				return;
			}
			else
			{
				if ($commentStart === false
					|| $tokens[$commentStart]['code'] !== T_DOC_COMMENT
				)
				{
					$phpcsFile->addError('Missing file doc comment', $errorToken, 'Missing');

					return;
				}
				else
				{

					// Extract the header comment docblock.
					$commentEnd = $phpcsFile->findNext(
						T_DOC_COMMENT,
						($commentStart + 1),
						null,
						true
					);

					$commentEnd--;

					// Check if there is only 1 doc comment between the
					// open tag and class token.
					$nextToken = array(
						T_ABSTRACT,
						T_CLASS,
						T_FUNCTION,
						T_DOC_COMMENT,
					);

					$commentNext = $phpcsFile->findNext($nextToken, ($commentEnd + 1));
					if ($commentNext !== false
						&& $tokens[$commentNext]['code'] !== T_DOC_COMMENT
					)
					{
						// Found a class token right after comment doc block.
						$newlineToken = $phpcsFile->findNext(
							T_WHITESPACE,
							($commentEnd + 1),
							$commentNext,
							false,
							$phpcsFile->eolChar
						);

						if ($newlineToken !== false)
						{
							$newlineToken = $phpcsFile->findNext(
								T_WHITESPACE,
								($newlineToken + 1),
								$commentNext,
								false,
								$phpcsFile->eolChar
							);

							if ($newlineToken === false)
							{
								// No blank line between the class token and the doc block.
								// The doc block is most likely a class comment.
								$error = 'Missing file doc comment';
								$phpcsFile->addError($error, $errorToken, 'Missing');

								return;
							}
						}
					}//end if

					$comment = $phpcsFile->getTokensAsString(
						$commentStart,
						($commentEnd - $commentStart + 1)
					);

					// Parse the header comment docblock.
					try
					{
						$this->commentParser = new PHP_CodeSniffer_CommentParser_ClassCommentParser($comment, $phpcsFile);
						$this->commentParser->parse();
						$this->commentParserLink = new THMPHP_CodeSniffer_CommentParser_ClassCommentLinksParser($comment, $phpcsFile);
						$this->commentParserLink->parse();
					}
					catch (PHP_CodeSniffer_CommentParser_ParserException $e)
					{
						$line = ($e->getLineWithinComment() + $commentStart);
						$phpcsFile->addError($e->getMessage(), $line, 'FailedParse');

						return;
					}

					$comment = $this->commentParser->getComment();
					if (is_null($comment) === true)
					{
						$error = 'File doc comment is empty';
						$phpcsFile->addError($error, $commentStart, 'Empty');

						return;
					}

					// No extra newline before short description.
					$short        = $comment->getShortComment();
					$newlineCount = 0;
					$newlineSpan  = strspn($short, $phpcsFile->eolChar);
					if ($short !== '' && $newlineSpan > 0)
					{
						$error = 'Extra newline(s) found before file comment short description';
						$phpcsFile->addError($error, ($commentStart + 1), 'SpacingBefore');
					}

					$newlineCount = (substr_count($short, $phpcsFile->eolChar) + 1);

					// Exactly one blank line between short and long description.
					$long = $comment->getLongComment();
					if (empty($long) === false)
					{
						$between        = $comment->getWhiteSpaceBetween();
						$newlineBetween = substr_count($between, $phpcsFile->eolChar);
						if ($newlineBetween !== 2)
						{
							$error = 'There must be exactly one blank line between descriptions in file comment';
							$phpcsFile->addError($error, ($commentStart + $newlineCount + 1), 'DescriptionSpacing');
						}

						$newlineCount += $newlineBetween;
					}

					// Exactly one blank line before tags if short description is present.
					$tags = $this->commentParser->getTagOrders();
					if (count($tags) > 1 && $short !== '' && $newlineSpan > 0)
					{
						$newlineSpan = $comment->getNewlineAfter();
						if ($newlineSpan !== 2)
						{
							$error = 'There must be exactly one blank line before the tags in file comment';
							if ($long !== '')
							{
								$newlineCount += (substr_count($long, $phpcsFile->eolChar) - $newlineSpan + 1);
							}

							$phpcsFile->addError($error, ($commentStart + $newlineCount), 'SpacingBeforeTags');
							$short = rtrim($short, $phpcsFile->eolChar . ' ');
						}
					}

//            // Check the PHP Version.
//            $this->processPHPVersion($commentStart, $commentEnd, $long);

					// Check each tag.          
					$this->processTags($commentStart, $commentEnd);
				}
			}
		}//end if

	}//end process()


//    /**
//     * Check that the PHP version is specified.
//     *
//     * @param int    $commentStart Position in the stack where the comment started.
//     * @param int    $commentEnd   Position in the stack where the comment ended.
//     * @param string $commentText  The text of the function comment.
//     *
//     * @return void
//     */
//    protected function processPHPVersion($commentStart, $commentEnd, $commentText)
//    {
//        if (strstr(strtolower($commentText), 'php version') === false) {
//            $error = 'PHP version not specified';
//             $this->currentFile->addWarning($error, $commentEnd, 'MissingVersion');
//        }
//
//    }//end processPHPVersion()


	/**
	 * Processes each required or optional tag.
	 *
	 * @param int $commentStart Position in the stack where the comment started.
	 * @param int $commentEnd   Position in the stack where the comment ended.
	 *
	 * @return void
	 */
	protected function processTags($commentStart, $commentEnd)
	{
		$docBlock    = (get_class($this) === 'Joomla_Sniffs_Commenting_FileCommentSniff') ? 'file' : 'class';
		$foundTags   = $this->commentParser->getTagOrders();
		$orderIndex  = 0;
		$indentation = array();
		$longestTag  = 0;
		$errorPos    = 0;

		foreach ($this->tags as $tag => $info)
		{
			// Required tag missing.
			if ($info['required'] === true && in_array($tag, $foundTags) === false)
			{
				$error = 'Missing @%s tag in %s comment';
				$data  = array(
					$tag,
					$docBlock,
				);
				$this->currentFile->addError($error, $commentEnd, 'MissingTag', $data);
				continue;
			}

			// Get the line number for current tag.
			$tagName = ucfirst($tag);
			if ($info['allow_multiple'] === true)
			{
				$tagName .= 's';
			}

			$getMethod  = 'get' . $tagName;
			$tagElement = $this->commentParser->$getMethod();
			if (is_null($tagElement) === true || empty($tagElement) === true)
			{
				continue;
			}

			$errorPos = $commentStart;
			if (is_array($tagElement) === false)
			{
				$errorPos = ($commentStart + $tagElement->getLine());
			}

			// Get the tag order.
			$foundIndexes = array_keys($foundTags, $tag);

			if (count($foundIndexes) > 1)
			{
				// Multiple occurance not allowed.
				if ($info['allow_multiple'] === false)
				{
					$error = 'Only 1 @%s tag is allowed in a %s comment';
					$data  = array(
						$tag,
						$docBlock,
					);
					$this->currentFile->addError($error, $errorPos, 'DuplicateTag', $data);
				}
				else
				{
					// Make sure same tags are grouped together.
					$i     = 0;
					$count = $foundIndexes[0];
					foreach ($foundIndexes as $index)
					{
						if ($index !== $count)
						{
							$errorPosIndex
								   = ($errorPos + $tagElement[$i]->getLine());
							$error = '@%s tags must be grouped together';
							$data  = array($tag);
							$this->currentFile->addError($error, $errorPosIndex, 'TagsNotGrouped', $data);
						}

						$i++;
						$count++;
					}
				}
			}//end if

			// Check tag order.
			if ($foundIndexes[0] > $orderIndex)
			{
				$orderIndex = $foundIndexes[0];
			}
			else
			{
				if (is_array($tagElement) === true && empty($tagElement) === false)
				{
					$errorPos += $tagElement[0]->getLine();
				}

				$error = 'The @%s tag is in the wrong order; the tag %s';
				$data  = array(
					$tag,
					$info['order_text'],
				);
				$this->currentFile->addError($error, $errorPos, 'WrongTagOrder', $data);
			}

			// Store the indentation for checking.
			$len = strlen($tag);
			if ($len > $longestTag)
			{
				$longestTag = $len;
			}

			if (is_array($tagElement) === true)
			{
				foreach ($tagElement as $key => $element)
				{
					$indentation[] = array(
						'tag'   => $tag,
						'space' => $this->getIndentation($tag, $element),
						'line'  => $element->getLine(),
					);
				}
			}
			else
			{
				$indentation[] = array(
					'tag'   => $tag,
					'space' => $this->getIndentation($tag, $tagElement),
				);
			}

			$method = 'process' . $tagName;
			if (method_exists($this, $method) === true)
			{
				// Process each tag if a method is defined.
				call_user_func(array($this, $method), $errorPos);
			}
			else
			{
				if (is_array($tagElement) === true)
				{
					foreach ($tagElement as $key => $element)
					{
						$element->process(
							$this->currentFile,
							$commentStart,
							$docBlock
						);
					}
				}
				else
				{
					$tagElement->process(
						$this->currentFile,
						$commentStart,
						$docBlock
					);
				}
			}
		}//end foreach

		foreach ($indentation as $indentInfo)
		{
			if ($indentInfo['space'] !== 0
				// Joomla change: allow for 2 space gap.
				&& $indentInfo['space'] !== ($longestTag + 2)
			)
			{
				$expected = (($longestTag - strlen($indentInfo['tag'])) + 2);
				$space    = ($indentInfo['space'] - strlen($indentInfo['tag']));
				$error    = '@%s tag comment indented incorrectly; expected %s spaces but found %s';
				$data     = array(
					$indentInfo['tag'],
					$expected,
					$space,
				);

				$getTagMethod = 'get' . ucfirst($indentInfo['tag']);

				if ($this->tags[$indentInfo['tag']]['allow_multiple'] === true)
				{
					$line = $indentInfo['line'];
				}
				else
				{
					$tagElem = $this->commentParser->$getTagMethod();
					$line    = $tagElem->getLine();
				}

				$this->currentFile->addError($error, ($commentStart + $line), 'TagIndent', $data);
			}
		}

	}//end processTags()


	/**
	 * Get the indentation information of each tag.
	 *
	 * @param string                                   $tagName    The name of the
	 *                                                             doc comment
	 *                                                             element.
	 * @param PHP_CodeSniffer_CommentParser_DocElement $tagElement The doc comment
	 *                                                             element.
	 *
	 * @return void
	 */
	protected function getIndentation($tagName, $tagElement)
	{
		if ($tagElement instanceof PHP_CodeSniffer_CommentParser_SingleElement)
		{
			if ($tagElement->getContent() !== '')
			{
				return (strlen($tagName) + substr_count($tagElement->getWhitespaceBeforeContent(), ' '));
			}
		}
		else
		{
			if ($tagElement instanceof PHP_CodeSniffer_CommentParser_PairElement)
			{
				if ($tagElement->getValue() !== '')
				{
					return (strlen($tagName) + substr_count($tagElement->getWhitespaceBeforeValue(), ' '));
				}
			}
		}

		return 0;

	}//end getIndentation()


	/**
	 * Process the category tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processCategory($errorPos)
	{
		$category = $this->commentParser->getCategory();
		if ($category !== null)
		{
			$content = $category->getContent();
			if (empty($content) === true)
			{
				$error = 'Content missing for @category tag in file comment';
				$this->currentFile->addError($error, $errorPos, 'EmptyCategory');
			}
			else
			{
				if ($content != 'Joomla component' && $content != 'Joomla module' && $content != 'Joomla plugin' && $content != 'Joomla library')
				{
					$error = 'Invalid category "%s" in doc comment; consider "Category: "Joomla component", "Joomla module", "Joomla plugin" or "Joomla library" instead';
					$data  = array($content);
					$this->currentFile->addError($error, $errorPos, 'InvalidCategory', $data);
				}
			}
		}

	}//end processCategory()


	/**
	 * Process the package tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processPackage($errorPos)
	{
		$package = $this->commentParser->getPackage();
		if ($package !== null)
		{
			$content = $package->getContent();
			$matches = array();
			if (empty($content) === true)
			{
				$error = 'Content missing for @package tag in doc comment';
				$this->currentFile->addError($error, $errorPos, 'EmptyPackage');
			}
			else
			{
				if ((strstr($content, 'THM_') === false))
				{
					$error = 'Invalid package "%s" in doc comment; contains "THM_" "Package: THM_<Name>" instead';
					$data  = array($content);
					$this->currentFile->addError($error, $errorPos, 'InvalidPackage', $data);
				}
			}
		}
	}//end processPackage()


	/**
	 * Process the subpackage tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processSubPackage($errorPos)
	{
		$subpackage = $this->commentParser->getSubpackage();
		if ($subpackage !== null)
		{
			$content = $subpackage->getContent();
			$matches = array();
			if (empty($content) === true)
			{
				$error = 'Content missing for @subpackage tag in doc comment';
				$this->currentFile->addError($error, $errorPos, 'EmptySubPackage');
			}
			else
			{
				if ((strstr($content, 'com_thm_') === false) && (strstr($content, 'mod_thm_') === false) && (strstr($content, 'plg_thm_') === false) && (strstr($content, 'lib_thm_') === false))
				{
					$error = 'Invalid name in subpackage, ' . $content . ' in doc comment; consider "subpackage: "com_thm_<package_name>", "mod_thm_<package_name>", "plg_thm_<package_name>" or "lib_thm_<package_name>" instead';
					$data  = array($content);
					$this->currentFile->addError($error, $errorPos, 'InvalidSubPackage.Name', $data);
				}

				if (strstr($content, 'com_thm_'))
				{
					if ((strstr($content, '.site') === false) && (strstr($content, '.admin') === false) && (strstr($content, '.general') === false))
					{
						$error = 'Invalid type in subpackage, "%s" in doc comment; example "subpackage: "com_thm_<name>.general", "com_thm_<name>.site" or "com_thm_<name>.admin"';
						$data  = array($content);
						$this->currentFile->addError($error, $errorPos, 'InvalidSubPackage.Type', $data);
					}
				}
			}
		}
	}//end processSubPackage()


	/**
	 * Process the author tag(s) that this header comment has.
	 *
	 * This function is different from other _process functions
	 * as $authors is an array of SingleElements, so we work out
	 * the errorPos for each element separately
	 *
	 * @param int $commentStart The position in the stack where
	 *                          the comment started.
	 *
	 * @return void
	 */
	protected function processAuthors($commentStart)
	{
		$authors = $this->commentParser->getAuthors();
		// Report missing return.
		if (empty($authors) === false)
		{
			foreach ($authors as $author)
			{
				$errorPos = ($commentStart + $author->getLine());
				$content  = $author->getContent();
				if ($content !== '')
				{
					$local = '\da-zA-Z-_+';
					// Dot character cannot be the first or last character
					// in the local-part.
					$localMiddle = $local . '.\w';
					if (strstr($content, ',') === false
						|| preg_match('/^([^<]*)\s+<([' . $local . '][' . $localMiddle . ']*[' . $local . ']@[\da-zA-Z][-.\w]*[\da-zA-Z]\.[a-zA-Z]{2,7})>$/', $content) === 0
						|| strstr($content, 'thm.de') === false
					)
					{
						$error = 'Content of the @author tag must be in the form "Display Name, <firstname.secondname@[department].thm.de>"';
						$this->currentFile->addError($error, $errorPos, 'InvalidAuthors');
					}
				}
				else
				{
					$error    = 'Content missing for @author tag in %s comment';
					$docBlock = (get_class($this) === 'PEAR_Sniffs_Commenting_FileCommentSniff') ? 'file' : 'class';
					$data     = array($docBlock);
					$this->currentFile->addError($error, $errorPos, 'EmptyAuthors', $data);
				}
			}
		}

	}//end processAuthors()


	/**
	 * Process the copyright tags.
	 *
	 * @param int $commentStart The position in the stack where
	 *                          the comment started.
	 *
	 * @return void
	 */
	protected function processCopyrights($commentStart)
	{
		$copyrights = $this->commentParser->getCopyrights();
		foreach ($copyrights as $copyright)
		{
			$errorPos = ($commentStart + $copyright->getLine());
			$content  = $copyright->getContent();
			if ($content !== '')
			{
				$matches = array();
				if (preg_match('/^.*?([0-9]{4})((.{1})([0-9]{4}))? (.+)$/', $content, $matches) !== 0)
				{
					// Check earliest-latest year order.
					if ($matches[3] !== '')
					{
						if ($matches[3] !== '-')
						{
							$error = 'A hyphen must be used between the earliest and latest year';
							$this->currentFile->addError($error, $errorPos, 'CopyrightHyphen');
						}

						if ($matches[4] !== '' && $matches[4] < $matches[1])
						{
							$error = "Invalid year span \"$matches[1]$matches[3]$matches[4]\" found; consider \"$matches[4]-$matches[1]\" instead";
							$this->currentFile->addWarning($error, $errorPos, 'InvalidCopyright');
						}
					}
				}
				$pieces = explode(" ", $content);
				if (!is_numeric($pieces[0]))
				{
					$error = 'Content of the @copyright tag must be in the form "<year> TH Mittelhessen"';
					$this->currentFile->addError($error, $errorPos, 'InvalidCopyright');
				}
				if ($pieces[1] != 'TH')
				{
					$error = 'Content of the @copyright tag must be in the form "<year> TH Mittelhessen"';
					$this->currentFile->addError($error, $errorPos, 'InvalidCopyright');
				}
				if ($pieces[2] != 'Mittelhessen')
				{
					$error = 'Content of the @copyright tag must be in the form "<year> TH Mittelhessen"';
					$this->currentFile->addError($error, $errorPos, 'InvalidCopyright');
				}
			}
			else
			{
				$error = '@copyright tag must contain a year and the name of the copyright holder';
				$this->currentFile->addError($error, $errorPos, 'EmptyCopyright');
			}//end if
		}//end if

	}//end processCopyrights()


	/**
	 * Process the license tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processLicense($errorPos)
	{
		$license = $this->commentParser->getLicense();
		if ($license !== null)
		{
			$value   = $license->getValue();
			$comment = $license->getComment();
			if ($value === '' || $comment === '')
			{
				$error = '@license tag must contain a URL and a license name';
				$this->currentFile->addError($error, $errorPos, 'EmptyLicense');
			}
		}

	}//end processLicense()

	/**
	 * Process the version tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processVersion($errorPos)
	{
		$version = $this->commentParser->getVersion();
		if ($version !== null)
		{
			$content = $version->getContent();
			$matches = array();
			if (empty($content) === true)
			{
				$error = 'Content missing for @version tag in file comment';
				$this->currentFile->addError($error, $errorPos, 'EmptyVersion');
			}
			else
			{
				if (!preg_match("/v([0-9]).([0-9]).([0-9])/", $content))
				{
					$error = 'Invalid version "' . $content . '" in file comment; consider "v<MajorRelease.MinorRelease.MaintenanceRelease>" instead. Example: "v1.0.0"';
					$data  = array($content);
					$this->currentFile->addError($error, $errorPos, 'InvalidVersion');
				}
			}
		}

	}//end processVersion()

	/**
	 * Process the link tag.
	 *
	 * @param int $errorPos The line number where the error occurs.
	 *
	 * @return void
	 */
	protected function processLinks($errorPos)
	{
		$link = $this->commentParserLink->getLink();
		if ($link !== null)
		{
			$content = $link->getValue();
			$matches = array();
			if (empty($content) === true)
			{
				$error = 'Content missing for @link tag in file comment';
				$this->currentFile->addError($error, $errorPos, 'EmptyLink');
			}
			if ($content != 'www.mni.thm.de' && $content != 'www.thm.de')
			{
				$error = 'Invalid link "%s" in file comment; consider "www.mni.thm.de" or "www.thm.de" instead';
				$this->currentFile->addError($error, $errorPos, 'InvalidLink');
			}
		}

	}//end processLink()

}//end class

?>
