<?php

/**
 * Wikitext scripting infrastructure for MediaWiki: base classes.
 * Copyright (C) 2012 Victor Vasiliev <vasilvv@gmail.com> et al
 * http://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * Base class for all script engines. Includes all code
 * not related to particular modules, like tracking links between
 * modules or loading module texts.
 */
abstract class ScribuntoEngineBase {
	protected
		$parser,
		$title,
		$options,
		$modules = array();

	/**
	 * Creates a new module object within this engine
	 */
	abstract protected function newModule( $text, $chunkName );

	/**
	 * Constructor.
	 * 
	 * @param $options Associative array of options:
	 *    - parser:            A Parser object
	 */
	public function __construct( $options ) {
		$this->options = $options;
		if ( isset( $options['parser'] ) ) {
			$this->parser = $options['parser'];
		}
		if ( isset( $options['title'] ) ) {
			$this->title = $options['title'];
		}
	}

	public function setTitle( $title ) {
		$this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	public function newException( $message, $params = array() ) {
		return new ScribuntoException( $message, $this->getDefaultExceptionParams() + $params );
	}

	public function getDefaultExceptionParams() {
		$params = array();
		if ( $this->title ) {
			$params['title'] = $this->title;
		}
		return $params;
	}

	/**
	 * Load a module from some parser-defined template loading mechanism and 
	 * register a parser output dependency.
	 *
	 * Does not initialize the module, i.e. do not expect it to complain if the module
	 * text is garbage or has syntax error. Returns a module or null if it doesn't exist.
	 *
	 * @param $title The title of the module
	 * @return ScribuntoEngineModule
	 */
	function fetchModuleFromParser( Title $title ) {
		list( $text, $finalTitle ) = $this->parser->fetchTemplateAndTitle( $title );
		if ( $text === false ) {
			return null;
		}

		$key = $finalTitle->getPrefixedDBkey();
		if ( !isset( $this->modules[$key] ) ) {
			$this->modules[$key] = $this->newModule( $text, $key );
		}
		return $this->modules[$key];
	}

	/**
	 * Validates the script and returns a Status object containing the syntax 
	 * errors for the given code.
	 * 
	 * @param $code Code to validate
	 * @param $title Title of the code page
	 * @return Status
	 */
	function validate( $text, $chunkName = false ) {
		$module = $this->newModule( $text, $chunkName );
		return $module->validate();
	}

	/**
	 * Allows the engine to append their information to the limits
	 * report.
	 */
	public function getLimitsReport() {
		/* No-op by default */
		return '';
	}

	/**
	 * Get the language for GeSHi syntax highlighter.
	 */
	function getGeSHiLanguage() {
		return false;
	}
	
	/**
	 * Get the language for Ace code editor.
	 */
	function getCodeEditorLanguage() {
		return false;
	}

	public function getParser() {
		return $this->parser;
	}
}

/**
 * Class that represents a module. Responsible for initial module parsing
 * and maintaining the contents of the module.
 */
abstract class ScribuntoModuleBase {
	var $engine, $code, $chunkName;

	public function __construct( $engine, $code, $chunkName ) {
		$this->engine = $engine;
		$this->code = $code;
		$this->chunkName = $chunkName;
	}

	/** Accessors **/
	public function getEngine()     { return $this->engine; }
	public function getCode()       { return $this->code; }
	public function getChunkName()  { return $this->chunkName; }

	/**
	 * Validates the script and returns a Status object containing the syntax 
	 * errors for the given code.
	 * 
	 * @param $code Code to validate
	 * @param $title Title of the code page
	 * @return Status
	 */
	abstract public function validate();
	
	/**
	 * Invoke the function with the specified name.
	 * 
	 * @return string
	 */
	abstract public function invoke( $name, $args, $frame );
}

