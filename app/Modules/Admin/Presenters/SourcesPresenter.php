<?php declare(strict_types=1);

namespace App\Modules\Admin\Presenters;

use App\Modules\Admin\Components\SourceForm\SourceForm;
use App\Modules\Admin\Components\SourceForm\SourceFormFactoryInterface;
use App\Modules\Admin\Components\SourcesTable\SourcesTable;
use App\Modules\Admin\Components\SourcesTable\SourcesTableFactoryInterface;
use App\Modules\Admin\Model\SourceModel;
use App\Modules\Admin\Model\SourceService;
use App\Modules\Core\Utils\DateTime;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime as NetteDateTime;

final class SourcesPresenter extends AbstractBasePresenter
{
    /**
     * @var SourceFormFactoryInterface
     */
    private $sourceFormFactory;

    /**
     * @var SourcesTableFactoryInterface
     */
    private $sourcesTableFactory;

    /**
     * @var SourceModel
     */
    private $sourceModel;

    /**
     * @var SourceService
     */
    private $sourceService;

    public function __construct(
        SourceService $sourceService,
        SourceModel $sourceModel,
        SourcesTableFactoryInterface $sourcesTableFactory,
        SourceFormFactoryInterface $sourceFormFactory
    ) {
        $this->sourceService = $sourceService;
        $this->sourceModel = $sourceModel;
        $this->sourcesTableFactory = $sourcesTableFactory;
        $this->sourceFormFactory = $sourceFormFactory;
    }

    public function actionCreate(): void
    {
        $defaults = [
            'frequencyNumber' => 1,
            'nextCheck' => (new NetteDateTime('+1 days'))->format(DateTime::DATE_FORMAT),
            'createOrganiser' => true,
        ];
        $this['sourceForm-form']->setDefaults($defaults);
    }

    protected function createComponentSourceForm(): SourceForm
    {
        $control = $this->sourceFormFactory->create();

        $control->onCreate[] = function (ActiveRow $source): void {
            $this->flashMessage($this->translator->translate('admin.sourceForm.success'), 'success');

            // Crawl recently added source
            $addedEvents = $this->sourceService->crawlSource($source);
            if ($addedEvents > 0) {
                $this->flashMessage($this->translator->translate('admin.events.crawlSources.success',
                    $addedEvents, ['events' => $addedEvents]), 'success');
            } else {
                $this->flashMessage($this->translator->translate('admin.events.crawlSources.noEvents'));
            }

            $this->redirect('Sources:default');
        };

        $control->onUpdate[] = function (ActiveRow $source): void {
            $this->flashMessage($this->translator->translate('admin.sourceForm.success'), 'success');
            $this->redirect('Sources:update', ['id' => $source->id]);
        };

        return $control;
    }

    protected function createComponentSourcesTable(): SourcesTable
    {
        return $this->sourcesTableFactory->create($this->sourceModel->getAll()->select('id, name, url'));
    }
}
