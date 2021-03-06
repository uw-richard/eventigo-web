<?php declare(strict_types=1);

namespace App\Modules\Admin\Model;

use App\Modules\Core\Model\EventSeriesModel;
use App\Modules\Core\Model\OrganiserModel;
use Nette\Database\Table\IRow;

final class OrganiserService
{
    /**
     * @var OrganiserModel
     */
    private $organiserModel;

    /**
     * @var EventSeriesModel
     */
    private $eventSeriesModel;

    public function __construct(EventSeriesModel $eventSeriesModel, OrganiserModel $organiserModel)
    {
        $this->eventSeriesModel = $eventSeriesModel;
        $this->organiserModel = $organiserModel;
    }

    /**
     * @return array|\Nette\Database\Table\IRow[]
     */
    public function getOrganisersSeries(): array
    {
        return $this->organiserModel->getAll()
            ->select('organisers.name AS organiser')
            ->select(':events_series.name AS series')
            ->select(':events_series.id AS seriesId')
            ->fetchAll();
    }

    /**
     * @param array|\Nette\Database\Table\IRow[] $series
     * @return array|\Nette\Database\Table\IRow[]
     */
    public static function formatSeriesForSelect(array $series): array
    {
        $result = [];

        foreach ($series as $item) {
            $result[$item->seriesId] = $item->organiser
                . ($item->series && $item->organiser !== $item->series
                    ? ': ' . $item->series
                    : '');
        }

        return $result;
    }

    public function createOrganiser(string $name, string $url): IRow
    {
        $organiser = $this->organiserModel->insert([
            'name' => $name,
        ]);

        $this->eventSeriesModel->insert([
            'organiser_id' => $organiser->id,
            'name' => $name,
            'url' => $url,
        ]);

        return $organiser;
    }
}
