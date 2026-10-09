<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Kernel;
use Sulu\Component\HttpKernel\SuluKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Uses the data of App\DataFixtures\TeaserReproducerFixtures, the same as in the admin after "sulu:build dev".
 */
class TeaserSelectionSharedInstanceTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true, $options['sulu_context'] ?? SuluKernel::CONTEXT_WEBSITE);
    }

    protected function setUp(): void
    {
        $adminKernel = static::createKernel(['sulu_context' => SuluKernel::CONTEXT_ADMIN]);
        $application = new Application($adminKernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(['command' => 'sulu:build', 'target' => 'dev', '--destroy' => true]);
        $input->setInteractive(false);
        $output = new BufferedOutput();
        $exitCode = $application->run($input, $output);
        self::assertSame(0, $exitCode, $output->fetch());

        $adminKernel->shutdown();
    }

    public function testItemOverridesOfOneTeaserSelectionDoNotLeakIntoAnother(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', 'http://localhost/teaser-reproducer');

        self::assertResponseIsSuccessful();

        $footerTeaser = $crawler->filter('#footer_teasers article');
        self::assertSame('Footer link title', $footerTeaser->attr('data-title'));
        self::assertCount(0, $footerTeaser->filter('img'));

        $contentTeaser = $crawler->filter('#teasers article');
        // fails on 3.0.10: the edited footer item was merged into the Teaser instance shared by both selections
        self::assertSame('Excerpt title', $contentTeaser->attr('data-title'));
        self::assertSame('Excerpt description', $contentTeaser->attr('data-description'));
        self::assertCount(1, $contentTeaser->filter('img'));
    }
}
