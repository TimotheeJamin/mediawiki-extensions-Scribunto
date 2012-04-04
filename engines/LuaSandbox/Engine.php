<?php

class LuaSandboxEngine extends ScriptingEngineBase {
	public $mSandbox, $mLoaded = false;

	public function newModule( $title, $code, $revisionID, $source ) {
		return new LuaSandboxEngineModule( $this, $title, $code, $revisionID, $source );
	}

	public function load() {
		if( $this->mLoaded ) {
			return;
		}

		if( !MWInit::classExists( 'luasandbox' ) ) {
			throw new MWException( 'luasandbox PHP extension is not installed' );
		}

		$this->mSandbox = new LuaSandbox;
		$this->mSandbox->setMemoryLimit( $this->mOptions['memoryLimit'] );
		$this->mSandbox->setCPULimit( $this->mOptions['maxCPU'] );
		$this->mSandbox->registerLibrary( 'mw', array( 'import' => array( $this, 'importModule' ) ) );
		
		$this->mLoaded = true;
	}

	protected function updateOptions() {
		if( $this->mLoaded ) {
			$this->mSandbox->setMemoryLimit( $this->mOptions['memoryLimit'] );
			$this->mSandbox->setCPULimit( $this->mOptions['maxCPU'] );
		}
	}

	protected function getModuleClassName() {
		return 'LuaSandboxEngineModule';
	}

	public function getDefaultOptions() {
		return array(
			'memoryLimit' => 50 * 1024 * 1024,
			'maxCPU' => 7,
		);
	}

	public function getGeSHiLangauge() {
		return 'lua';
	}
	
	public function getCodeEditorLanguage() {
		return 'lua';
	}
	
	public function getLimitsReport() {
		$this->load();
		
		$usage = $this->mSandbox->getMemoryUsage();
		if( $usage < 8 * 1024 ) {
			$usageStr = $usage . " bytes";
		} elseif( $usage < 8 * 1024 * 1024 ) {
			$usageStr = round( $usage / 1024, 2 ) . " kilobytes";
		} else {
			$usageStr = round( $usage / 1024 / 1024, 2 ) . " megabytes";
		}

		return "Lua scripts memory usage: {$usageStr}\n";
	}
	
	function importModule() {
		$args = func_get_args();
		if( count( $args ) < 1 ) {
			// FIXME: LuaSandbox PHP extension should provide proper context
			throw new ScriptingException( 'toofewargs', 'common', null, null, array( 'mw.import' ) );
		}

		$module = $this->getModule( $args[0] );
		$module->initialize();
		return $module->mContents;
	}
}

class LuaSandboxEngineModule extends ScriptingModuleBase {
	protected $mInitialized;

	function initialize() {
		if( $this->mInitialized ) {
			return;
		}
		$this->mEngine->load();

		// FIXME: caching?

		try {
			$this->mBody = $this->mEngine->mSandbox->loadString(
				$this->mCode, 
				// Prepending an "@" to the chunk name makes Lua think it is a file name
				'@' . $this->getTitle()->getPrefixedDBkey() );
			$output = $this->mBody->call();
		} catch( LuaSandboxError $e ) {
			throw new ScriptingException( 'error', 'luasandbox', null, null, array( $e->getMessage() ) );
		}
		
		if( !$output ) {
			throw new ScriptingException( 'noreturn', 'luasandbox' );
		}
		if( count( $output ) > 2 ) {
			throw new ScriptingException( 'toomanyreturns', 'luasandbox' );
		}
		if( !is_array( $output[0] ) ) {
			throw new ScriptingException( 'notarrayreturn', 'luasandbox' );
		}
		
		$this->mContents = $output[0];
		$this->mFunctions = array();
		foreach( $this->mContents as $key => $content ) {
			if( $content instanceof LuaSandboxFunction )
				$this->mFunctions[] = $key;
		}

		$this->mInitialized = true;
	}

	function getFunction( $name ) {
		$this->initialize();

		if( isset( $this->mContents[$name] ) ) {
			return new LuaSandboxEngineFunction( $this, $name, $this->mContents[$name] );
		} else {
			return null;
		}
	}

	function getFunctions() {
		$this->initialize();
		return $this->mFunctions;
	}
}

class LuaSandboxEngineFunction extends ScriptingFunctionBase {
	public function call( $args, $frame ) {
		try {
			$result = call_user_func_array( array( $this->mContents, 'call' ), $args );
		} catch( LuaSandboxError $e ) {
			throw new ScriptingException( 'error', 'luasandbox', null, null, array( $e->getMessage() ) );
		}
		
		if ( isset( $result[0] ) ) {
			return $result[0];
		} else {
			return null;
		}
	}
}
