<?php

class CloneGitRepo {

	public $args;
	
	/**
	 * 
	 * @global array $databaseConfig
	 */
	public function setUp() {
		global $databaseConfig;
		DB::connect($databaseConfig);
		chdir(BASE_PATH);
	}

	public function perform() {
		set_time_limit(0);
		$path = $this->args['path'];
		$repo = $this->args['repo'];
		$env = $this->args['env'];
		$logfile = DEPLOYNAUT_LOG_PATH . '/clonegitrepo.log';
		$fh = fopen($logfile, 'a');
		if(!$fh) {
			throw new RuntimeException('Can\'t open file "'.$logfile.'" for logging.');
		}

		// if an alternate user has been configured for clone, run the command as that user
		$user = Injector::inst()->get('DNData')->getGitUser();

		if(file_exists($path)) {
			if($user) {
				exec(sprintf('sudo -u %s rm -rf %s', $user, $path));
			} else {
				exec(sprintf('rm -rf %s', $path));
			}
		}

		fwrite($fh, '['.date('Y-m-d H:i:s').'] Cloning ' . $repo.' to ' . $path . PHP_EOL);
		echo "[-] CloneGitRepo starting" . PHP_EOL;

		// using git clone straight via system call since doing it via the
		// Gitonomy\Git\Admin times out. Silly.

		if($user) {
			$command = sprintf('sudo -u %s git clone --bare -q %s %s', $user, $repo, $path);
		} else {
			$command = sprintf('git clone --bare -q %s %s', $repo, $path);
		}

		fwrite($fh, '['.date('Y-m-d H:i:s').'] Running command: ' . $command . PHP_EOL);

		$process = new \Symfony\Component\Process\Process($command);
		$process->setEnv($env);
		$process->setTimeout(3600);
		$process->run();
		if(!$process->isSuccessful()) {
			throw new RuntimeException($process->getErrorOutput());
		}

		fwrite($fh, '['.date('Y-m-d H:i:s').'] Cloned ' . $repo . ' to ' . $path . PHP_EOL);
	}

}
