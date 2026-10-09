<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\MediaBundle\Collection\Manager\CollectionManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * A target page with excerpt (title, description, image) and the page /teaser-reproducer, which references the target
 * page in two teaser_selection properties. Only the item in "footer_teasers" is edited.
 */
class TeaserReproducerFixtures extends Fixture implements OrderedFixtureInterface
{
    private const WEBSPACE = 'website';
    private const LOCALE = 'en';

    public function __construct(
        #[Autowire(service: 'sulu_message_bus')] private readonly MessageBusInterface $messageBus,
        #[Autowire(service: 'sulu_media.collection_manager')] private readonly CollectionManagerInterface $collectionManager,
        private readonly MediaManagerInterface $mediaManager,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $homepage = $manager->getRepository(Page::class)->findOneBy(['webspaceKey' => self::WEBSPACE, 'parent' => null]);
        if (!$homepage instanceof PageInterface) {
            throw new \RuntimeException('Homepage of webspace "website" not found, run "sulu:build dev" first.');
        }

        $collection = $this->collectionManager->save(['title' => 'Teaser reproducer', 'locale' => self::LOCALE, 'type' => ['id' => 1]]);
        // the media storage may move the uploaded file
        $imagePath = (string) \tempnam(\sys_get_temp_dir(), 'excerpt-image');
        \copy(__DIR__ . '/excerpt-image.png', $imagePath);
        $image = $this->mediaManager->save(
            new UploadedFile($imagePath, 'excerpt-image.png', 'image/png', null, true),
            ['title' => 'Excerpt image', 'locale' => self::LOCALE, 'collection' => $collection->getId()],
            null,
        );

        $targetPageUuid = $this->createPublishedPage($homepage->getUuid(), [
            'template' => 'default',
            'title' => 'Target page',
            'url' => '/target-page',
            'excerpt' => [
                'title' => 'Excerpt title',
                'description' => 'Excerpt description',
                'image' => ['id' => $image->getId()],
            ],
        ]);

        $this->createPublishedPage($homepage->getUuid(), [
            'template' => 'teaser_reproducer',
            'title' => 'Teaser reproducer',
            'url' => '/teaser-reproducer',
            'teasers' => ['items' => [
                ['id' => $targetPageUuid, 'type' => 'pages'],
            ]],
            // what the admin stores after editing the item: own title, image and description removed
            'footer_teasers' => ['items' => [
                ['id' => $targetPageUuid, 'type' => 'pages', 'title' => 'Footer link title', 'mediaId' => null, 'description' => null],
            ]],
        ]);
    }

    public function getOrder(): int
    {
        return 100;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createPublishedPage(string $parentUuid, array $data): string
    {
        $envelope = $this->messageBus->dispatch(new Envelope(
            new CreatePageMessage(self::WEBSPACE, $parentUuid, [...$data, 'locale' => self::LOCALE]),
            [new EnableFlushStamp()],
        ));
        /** @var PageInterface $page */
        $page = $envelope->last(HandledStamp::class)?->getResult();

        $this->messageBus->dispatch(new Envelope(
            new ApplyWorkflowTransitionPageMessage(['uuid' => $page->getUuid()], self::LOCALE, 'publish'),
            [new EnableFlushStamp()],
        ));

        return $page->getUuid();
    }
}
