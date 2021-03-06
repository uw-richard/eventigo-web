<?php declare(strict_types=1);

namespace App\Modules\Admin\Model;

use App\Modules\Core\Model\EventModel;
use App\Modules\Core\Model\EventSources\Facebook\FacebookEventSource;
use App\Modules\Core\Model\EventSources\Meetup\MeetupEventSource;
use App\Modules\Core\Model\EventSources\Srazy\SrazyEventSource;
use Nette\Database\Table\IRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Http\Url;

final class SourceService
{
    /**
     * @var SourceModel
     */
    private $sourceModel;

    /**
     * @var FacebookEventSource
     */
    private $fbSource;

    /**
     * @var SrazyEventSource
     */
    private $srazySource;

    /**
     * @var MeetupEventSource
     */
    private $meetupSource;

    /**
     * @var EventModel
     */
    private $eventModel;

    public function __construct(
        EventModel $eventModel,
        MeetupEventSource $meetupSource,
        SrazyEventSource $srazySource,
        FacebookEventSource $fbSource,
        SourceModel $sourceModel
    ) {
        $this->eventModel = $eventModel;
        $this->meetupSource = $meetupSource;
        $this->srazySource = $srazySource;
        $this->fbSource = $fbSource;
        $this->sourceModel = $sourceModel;
    }

    public function crawlSources(): int
    {
        $addedEvents = 0;

        // TODO Check sources by frequency
        $sources = $this->sourceModel->getAll();
        foreach ($sources as $source) {
            $addedEvents += $this->crawlSource($source);
        }

        return $addedEvents;
    }

    public function crawlSource(IRow $source): int
    {
        $addedEvents = 0;

        $events = [];
        $sourceUrl = new Url($source['url']);
        if (FacebookEventSource::isSource($source['url'])) {
            $pageId = trim($sourceUrl->getPath(), '/');
            $events = $this->fbSource->getEvents($pageId);
        } elseif (SrazyEventSource::isSource($source['url'])) {
            $pathParts = array_filter(explode('/', $sourceUrl->getPath()));
            $series = reset($pathParts);
            $events = $this->srazySource->getEvents($series);
        } elseif (MeetupEventSource::isSource($source['url'])) {
            $pathParts = array_filter(explode('/', $sourceUrl->getPath()));
            $group = reset($pathParts);
            $events = $this->meetupSource->getEvents($group);
        }

        foreach ($events as $event) {
            $existingEvent = $this->eventModel->findExistingEvent($event);

            if (! $existingEvent) {
                try {
                    $this->eventModel->insert([
                        'name' => $event->getName(),
                        'description' => $event->getDescription(),
                        'start' => $event->getStart(),
                        'end' => $event->getEnd(),
                        'origin_url' => $event->getOriginUrl(),
                        'image' => $event->getImage(),
                        'rate' => $event->getRate(),
                        'event_series_id' => $source['event_series_id'],
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    continue;
                }

                ++$addedEvents;
            }
        }

        return $addedEvents;
    }
}
