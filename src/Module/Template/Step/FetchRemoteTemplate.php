<?php

namespace Couscous\Module\Template\Step;

use Couscous\CommandRunner\CommandRunner;
use Couscous\Model\Project;
use Couscous\Step;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Fetch a remote template.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class FetchRemoteTemplate implements Step
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var CommandRunner
     */
    private $commandRunner;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Temporarily save the template directory if we are in preview
     * to avoid cloning the repository every time.
     *
     * In theory we shouldn't store state in this object because it's a service
     * but we would need extensive change to avoid that.
     *
     * @var string
     */
    private $templateDirectory;

    public function __construct(
        Filesystem $filesystem,
        CommandRunner $commandRunner,
        LoggerInterface $logger
    ) {
        $this->filesystem    = $filesystem;
        $this->commandRunner = $commandRunner;
        $this->logger        = $logger;
    }

    public function __invoke(Project $project)
    {
        // In preview we avoid cloning the repository every time
        if ($project->regenerate && $this->templateDirectory) {
            $project->metadata['template.directory'] = $this->templateDirectory;

            return;
        }

        $templateUrl = $project->metadata['template.url'];

        if ($templateUrl === null) {
            return;
        }

        $directory = $this->fetchGitTemplate($templateUrl);

        $this->templateDirectory = $directory;
        $project->metadata['template.directory'] = $directory;
    }

    private function fetchGitTemplate($gitUrl)
    {
        $this->logger->notice('Fetching template from {url}', ['url' => $gitUrl]);

        $directory = $this->createTempDirectory('couscous_template_');

        $this->commandRunner->run("git clone $gitUrl $directory");

        return $directory;
    }

    private function createTempDirectory($prefix)
    {
        $tempFile = tempnam(sys_get_temp_dir(), $prefix);
        // Turn the temp file into a temp directory
        $this->filesystem->remove($tempFile);
        $this->filesystem->mkdir($tempFile);

        return $tempFile;
    }
}
