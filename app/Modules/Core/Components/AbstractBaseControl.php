<?php declare(strict_types=1);

namespace App\Modules\Core\Components;

use App\Modules\Core\Utils\DateTime;
use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Utils\DateTime as NetteDateTime;

abstract class AbstractBaseControl extends Control
{
    /**
     * @inject
     * @var \Kdyby\Translation\Translator
     */
    public $translator;

    public function render(): void
    {
        $this->template->render();
    }

    protected function createTemplate(): Template
    {
        /** @var Template $template */
        $template = parent::createTemplate();

        if ($file = $this->getTemplateDefaultFile()) {
            $template->setFile($file);
        }

        $template->addFilter('datetime', function (NetteDateTime $a, ?NetteDateTime $b = null) {
            DateTime::setTranslator($this->translator);

            return DateTime::eventsDatetimeFilter($a, $b);
        });

        return $template;
    }

    /**
     * Derives template path from class name.
     */
    protected function getTemplateDefaultFile(): ?string
    {
        $refl = $this->getReflection();
        $file = dirname($refl->getFileName()) . '/' . $refl->getShortName() . '.latte';

        return file_exists($file) ? $file : null;
    }
}
