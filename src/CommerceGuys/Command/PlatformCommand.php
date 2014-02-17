<?php

namespace CommerceGuys\Command;

use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\RefreshToken;
use Guzzle\Service\Client;
use Guzzle\Service\Description\ServiceDescription;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

class PlatformCommand extends Command
{
    protected $config;
    protected $oauth2Plugin;
    protected $accountClient;
    protected $platformClient;

    /**
     * Load configuration from the user's .platform file.
     *
     * Configuration is loaded only if $this->config hasn't been populated
     * already. This allows InitCommand to avoid writing the config file
     * before using the client for the first time.
     *
     * @return array The populated configuration array.
     */
    protected function loadConfig()
    {
        if (!$this->config) {
            $homeDir = trim(shell_exec('cd ~ && pwd'));
            $yaml = new Parser();
            $this->config = $yaml->parse(file_get_contents($homeDir . '/.platform'));
        }

        return $this->config;
    }

    /**
     * Return an instance of Oauth2Plugin.
     *
     * @return Oauth2Plugin
     */
    protected function getOauth2Plugin()
    {
        if (!$this->oauth2Plugin) {
            $this->loadConfig();
            $oauth2Client = new Client('https://marketplace.commerceguys.com/oauth2/token');
            $config = array(
                'username' => $this->config['email'],
                'password' => $this->config['password'],
                'client_id' => 'platform-cli',
            );
            $grantType = new PasswordCredentials($oauth2Client, $config);
            $refreshTokenGrantType = new RefreshToken($oauth2Client, $config);
            $this->oauth2Plugin = new Oauth2Plugin($grantType, $refreshTokenGrantType);
            if (!empty($this->config['access_token'])) {
                $this->oauth2Plugin->setAccessToken($this->config['access_token']);
            }
            if (!empty($this->config['refresh_token'])) {
                $this->oauth2Plugin->setRefreshToken($this->config['refresh_token']);
            }
        }

        return $this->oauth2Plugin;
    }

    /**
     * Return an instance of the Guzzle client for the Accounts endpoint.
     *
     * @return Client
     */
    protected function getAccountClient()
    {
        if (!$this->accountClient) {
            $description = ServiceDescription::factory(CLI_ROOT . '/services/accounts.json');
            $oauth2Plugin = $this->getOauth2Plugin();
            $this->accountClient = new Client();
            $this->accountClient->setDescription($description);
            $this->accountClient->addSubscriber($oauth2Plugin);
        }

        return $this->accountClient;
    }

    /**
     * Return an instance of the Guzzle client for the Platform endpoint.
     *
     * @param string $baseUrl The base url for API calls, usually the project URI.
     *
     * @return Client
     */
    protected function getPlatformClient($baseUrl)
    {
        if (!$this->platformClient) {
            $description = ServiceDescription::factory(CLI_ROOT . '/services/platform.json');
            $oauth2Plugin = $this->getOauth2Plugin();
            $this->platformClient = new Client(array('base_url' => $baseUrl));
            $this->platformClient->setDescription($description);
            $this->platformClient->addSubscriber($oauth2Plugin);
        }

        return $this->platformClient;
    }

    /**
     * Return the user's projects.
     *
     * The projects are persisted in config, relaoded in PlatformListCommand.
     * Most platform commands (such as the environment ones) have a project
     * base url, so this persistence allows them to avoid loading the platform
     * list each time.
     *
     * @param boolean $refresh Whether to refetch the list of projects.
     *
     * @return array The user's projects.
     */
    protected function getProjects($refresh = false)
    {
        $this->loadConfig();
        if (empty($this->config['projects']) || $refresh) {
            $accountClient = $this->getAccountClient();
            $data = $accountClient->getProjects();
            // Generate a machine name for each project and rekey the array.
            $projects = array();
            foreach ($data['projects'] as $project) {
                $machineName = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($project['name']));
                $projects[$machineName] = $project;
            }
            $this->config['projects'] = $projects;
        }

        return $this->config['projects'];
    }

    /**
     * Destructor: Writes the configuration to disk.
     */
    public function __destruct()
    {
        if (is_array($this->config)) {
            if ($this->client) {
                // Save the refresh and access tokens for next time.
                $this->config['access_token'] = $this->oauth2Plugin->getAccessToken();
                $this->config['refresh_token'] = $this->oauth2Plugin->getRefreshToken();
            }

            $dumper = new Dumper();
            $homeDir = trim(shell_exec('cd ~ && pwd'));
            file_put_contents($homeDir . '/.platform', $dumper->dump($this->config));
        }
    }

    /**
     * @return boolean Whether the user has configured the CLI.
     */
    protected function hasConfiguration()
    {
        $homeDir = trim(shell_exec('cd ~ && pwd'));
        return file_exists($homeDir . '/.platform');
    }
}
