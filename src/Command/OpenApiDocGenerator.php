<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Command;

use ChamberOrchestra\OpenApiDocBundle\Builder\DocumentBuilder;
use ChamberOrchestra\OpenApiDocBundle\Describer\OperationDescriber;
use ChamberOrchestra\OpenApiDocBundle\Exception\DescriberException;
use ChamberOrchestra\OpenApiDocBundle\Locator\Locator;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'openapi-doc:generate'
)]
class OpenApiDocGenerator extends AbstractCommand
{
    public function __construct(
        private KernelInterface $kernel,
        private Locator $locator,
        private OperationDescriber $operationDescriber,
        private DocumentBuilder $documentBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('src', null, InputOption::VALUE_OPTIONAL, 'Source code directory, default <project_dir>/src.')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output yaml file, default <project_dir>/doc.yaml.')
            ->addOption('proto', null, InputOption::VALUE_OPTIONAL, 'Proto file which will be included in output, default <project_dir>/proto.yaml.')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL)
            ->addOption('doc-version', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $title = $input->getOption('title') ?: 'API Documentation';
        $version = $input->getOption('doc-version') ?: '1.0.0';
        $src = $input->getOption('src') ?: $this->kernel->getProjectDir().'/src';
        $outputFile = $input->getOption('output') ?: $this->kernel->getProjectDir().'/doc.yaml';

        if (!is_dir($src)) {
            $this->io->error('Passed src is invalid');

            return Command::FAILURE;
        }
        $proto = $input->getOption('proto') ?: $this->kernel->getProjectDir().'/proto.yaml';

        $classes = $this->locator->locate($src);

        foreach ($classes as $class) {
            try {
                $this->operationDescriber->describe($class);
            } catch (DescriberException $e) {
                $this->io->warning(sprintf('Skipping class %s: %s', $class, $e->getMessage()));
            }
        }

        $protoData = [];
        if (file_exists($proto)) {
            try {
                $protoData = Yaml::parseFile($proto);
            } catch (Exception $exception) {
                $this->io->error('Error with proto file parsing: '.$exception->getMessage());

                return Command::FAILURE;
            }
        }

        $data = $this->documentBuilder->build($protoData, $version, $title);

        $this->write($data, $outputFile);

        $this->io->success('done');

        return 0;
    }

    private function write(array $data, string $file): void
    {
        $yaml = Yaml::dump($data, 10, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        // PHP array key coercion turns '200' into int 200, so the YAML dumper
        // outputs bare integer keys. OpenAPI 3.x requires string keys for HTTP
        // status codes. Quote every 3-digit numeric key that appears alone on a line.
        $yaml = preg_replace('/^( +)([1-5]\d{2}):$/m', '$1\'$2\':', $yaml);

        if (false === file_put_contents($file, $yaml)) {
            throw new \RuntimeException(sprintf(
                'Failed to write documentation to "%s". Check that the directory exists and is writable.',
                $file
            ));
        }
    }
}
