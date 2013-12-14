<?php
namespace Rocketeer\Tasks;

use Rocketeer\Traits\Task;

/**
 * Check if the server is ready to receive the application
 */
class Check extends Task
{
	/**
	 * The PHP extensions loaded on server
	 *
	 * @var array
	 */
	protected $extensions = array();

	 /**
	 * A description of what the Task does
	 *
	 * @var string
	 */
	protected $description = 'Check if the server is ready to receive the application';

	/**
	 * Run the Task
	 *
	 * @return  void
	 */
	public function execute()
	{
		$errors = array();
		$checks = $this->getChecks();

		foreach ($checks as $check => $error) {
			$argument = null;
			if (is_array($error)) {
				$argument = $error[0];
				$error    = $error[1];
			}

			// If the check fail, print an error message
			if (!$this->$check($argument)) {
				$errors[] = $error;
			}
		}

		// Return false if any error
		if (!empty($errors)) {
			$this->command->error(implode(PHP_EOL, $errors));

			return false;
		}

		// Display confirmation message
		$this->command->info('Your server is ready to deploy');

		return true;
	}

	/**
	 * Get the checks to execute
	 *
	 * @return array
	 */
	protected function getChecks()
	{
		$extension = 'The %s extension does not seem to be loaded on the server';
		$database  = $this->app['config']->get('database.default');
		$cache     = $this->app['config']->get('cache.driver');
		$session   = $this->app['config']->get('session.driver');

		return array(
			'checkScm'            => $this->scm->binary. ' could not be found',
			'checkPhpVersion'     => 'The version oh PHP on the server does not match Laravel\'s requirements',
			'checkComposer'       => 'Composer does not seem to be present on the server',
			'checkPhpExtension'   => array('mcrypt',  sprintf($extension, 'mcrypt')),
			'checkDatabaseDriver' => array($database, sprintf($extension, $database)),
			'checkCacheDriver'    => array($cache,    sprintf($extension, $cache)),
			'checkCacheDriver'    => array($session,  sprintf($extension, $session)),
		);
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// CHECKS /////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Check the presence of an SCM on the server
	 *
	 * @return boolean
	 */
	public function checkScm()
	{
		$this->command->comment('Checking presence of '.$this->scm->binary);
		$this->history[] = $this->scm->execute('check');

		return $this->remote->status() == 0;
	}

	/**
	 * Check if Composer is on the server
	 *
	 * @return boolean
	 */
	public function checkComposer()
	{
		$this->command->comment('Checking presence of Composer');

		return $this->getComposer();
	}

	/**
	 * Check if the server is ready to support PHP
	 *
	 * @return boolean
	 */
	public function checkPhpVersion()
	{
		$this->command->comment('Checking PHP version');
		$version = $this->run($this->php('-r "print PHP_VERSION;"'));

		return version_compare($version, '5.3.7', '>=');
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// HELPERS ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Check the presence of the correct database PHP extension
	 *
	 * @param  string $database
	 *
	 * @return boolean
	 */
	public function checkDatabaseDriver($database)
	{
		switch ($database) {
			case 'sqlite':
				return $this->checkPhpExtension('pdo_sqlite');

			case 'mysql':
				return $this->checkPhpExtension('mysql') and $this->checkPhpExtension('pdo_mysql');

			default:
				return true;
		}
	}

	/**
	 * Check the presence of the correct cache PHP extension
	 *
	 * @param  string $cache
	 *
	 * @return boolean
	 */
	public function checkCacheDriver($cache)
	{
		switch ($cache) {
			case 'memcached':
			case 'apc':
			case 'redis':
				return $this->checkPhpExtension($cache);

			default:
				return true;
		}
	}

	/**
	 * Check the presence of a PHP extension
	 *
	 * @param  string $extension    The extension
	 *
	 * @return boolean
	 */
	public function checkPhpExtension($extension)
	{
		$this->command->comment('Checking presence of '.$extension. ' extension');

		// Get the PHP extensions available
		if (!$this->extensions) {
			$this->extensions = (array) $this->run($this->php('-m'), false, true);
		}

		return in_array($extension, $this->extensions);
	}
}
