<?php
namespace TYPO3\Surf\CMS\Task;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Surf.CMS".*
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Composer\Exception\InvalidConfigurationException;
use TYPO3\Surf\Domain\Model\Node;
use TYPO3\Surf\Domain\Model\Application;
use TYPO3\Surf\Domain\Model\Deployment;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Surf\Domain\Model\Task;

/**
 * This task gets the TYPO3 configuration for both the source and the target system,
 * based on the given TYPO3_CONTEXTs
 */
class GetContextConfigurationTask extends Task {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Surf\Domain\Service\ShellCommandService
	 */
	protected $shell;

	/**
	 * @var array
	 */
	protected $requiredOptions = array('sourceContext', 'targetContext');

	/**
	 * Execute this task
	 *
	 * @param \TYPO3\Surf\Domain\Model\Node $node
	 * @param \TYPO3\Surf\Domain\Model\Application $application
	 * @param \TYPO3\Surf\Domain\Model\Deployment $deployment
	 * @param array $options
	 * @throws InvalidConfigurationException
	 * @throws \Exception
	 * @return void
	 */
	public function execute(Node $node, Application $application, Deployment $deployment, array $options = array()) {
		$this->assertRequiredOptionsExist($options);

		$repositoryUrl = $application->getOption('repositoryUrl');

		$temporaryDirectory = sys_get_temp_dir() . '/' . uniqid('bm_deploy');
		$this->shell->execute(sprintf('mkdir -p %s', $temporaryDirectory), $node, $deployment);

		// get configuration from project
		$localConfigFile =  'LocalConfiguration.php';
		$sourceConfigFile = 'AdditionalConfiguration.' . $options['sourceContext'] . '.php';
		$targetConfigFile = 'AdditionalConfiguration.' . $options['targetContext'] . '.php';
		$commands[] = sprintf('cd %s; git archive --remote=%s HEAD:typo3conf -- %s | tar -x', $temporaryDirectory, $repositoryUrl, $localConfigFile);
		$commands[] = sprintf('cd %s; git archive --remote=%s HEAD:typo3conf -- %s | tar -x', $temporaryDirectory, $repositoryUrl, $sourceConfigFile);
		$commands[] = sprintf('cd %s; git archive --remote=%s HEAD:typo3conf -- %s | tar -x', $temporaryDirectory, $repositoryUrl, $targetConfigFile);

		$localhost = new Node('localhost');
		$localhost->setHostname('localhost');

		$this->shell->executeOrSimulate($commands, $localhost, $deployment);

		try {
			$GLOBALS['TYPO3_CONF_VARS'] = include $temporaryDirectory . '/' . $localConfigFile;
			include $temporaryDirectory . '/' . $sourceConfigFile;
			$configSource = array(
				'sourceHost' => $GLOBALS['TYPO3_CONF_VARS']['DB']['host'],
				'sourceUser' => $GLOBALS['TYPO3_CONF_VARS']['DB']['username'],
				'sourcePassword' => $GLOBALS['TYPO3_CONF_VARS']['DB']['password'],
				'sourceDatabase' => $GLOBALS['TYPO3_CONF_VARS']['DB']['database']
			);
			include $temporaryDirectory . '/' . $targetConfigFile;
			$configTarget = array(
				'targetHost' => $GLOBALS['TYPO3_CONF_VARS']['DB']['host'],
				'targetUser' => $GLOBALS['TYPO3_CONF_VARS']['DB']['username'],
				'targetPassword' => $GLOBALS['TYPO3_CONF_VARS']['DB']['password'],
				'targetDatabase' => $GLOBALS['TYPO3_CONF_VARS']['DB']['database']
			);
			$application->setOption('contextConfiguration', array_merge($configSource, $configTarget));
			unlink($temporaryDirectory . '/' . $localConfigFile);
			unlink($temporaryDirectory . '/' . $sourceConfigFile);
			unlink($temporaryDirectory . '/' . $targetConfigFile);
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Simulate this task
	 *
	 * @param Node $node
	 * @param Application $application
	 * @param Deployment $deployment
	 * @param array $options
	 * @return void
	 */
	public function simulate(Node $node, Application $application, Deployment $deployment, array $options = array()) {
		$this->execute($node, $application, $deployment, $options);
	}

	/**
	 * @param array $options
	 * @throws \TYPO3\Surf\Exception\InvalidConfigurationException
	 */
	protected function assertRequiredOptionsExist(array $options) {
		foreach ($this->requiredOptions as $optionName) {
			if (!isset($options[$optionName])) {
				throw new \TYPO3\Surf\Exception\InvalidConfigurationException(sprintf('Required option "%s" is not set!', $optionName), 1405592631);
			}
		}
	}
}
