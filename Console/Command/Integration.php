<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Console\Command;

use Magmodules\Reloadify\Service\WebApi\Integration as CreateToken;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Selftest
 *
 * Perform tests on module
 */
class Integration extends Command
{

    /**
     * Command call name
     */
    public const COMMAND_NAME = 'reloadify:integration';
    public const COMMAND_OPTION_UPDATE = 'update';

    /**
     * @var CreateToken
     */
    private $createToken;

    /**
     * Selftest constructor.
     *
     * @param CreateToken $createToken
     */
    public function __construct(
        CreateToken $createToken
    ) {
        $this->createToken = $createToken;
        parent::__construct();
    }

    /**
     *  {@inheritdoc}
     */
    public function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Create or update integration');
        $this->addOption(
            self::COMMAND_OPTION_UPDATE,
            '-u',
            InputOption::VALUE_OPTIONAL,
            'Update token with new version (usage: --update=1)'
        );
        parent::configure();
    }

    /**
     *  {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $token = $this->createToken->execute($this->isUpdate($input));
            $output->writeln(sprintf('<info>Integration token: %s</info>', $token));
        } catch (\Exception $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @return bool
     */
    private function isUpdate(InputInterface $input): bool
    {
        if ($input->getOption(self::COMMAND_OPTION_UPDATE) == 1) {
            return true;
        }

        if ($input->getOption(self::COMMAND_OPTION_UPDATE) == 'true') {
            return true;
        }

        return false;
    }
}
