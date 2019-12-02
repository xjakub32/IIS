<?php

declare(strict_types=1);

namespace App\Presenters;
use Nette\Application\UI\Form;

use Nette;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    function privileges(string $role, string $alert = NULL, string $type = NULL, $bool = FALSE): bool
    {
        if($this->getUser()->isInRole('Admin'))
            return TRUE;
            
        else if($this->getUser()->isInRole('Vedoucí') && ($role == "Vedoucí" || $role == "Manažer" || $role == "Pracovník" || $role == "Zákazník"))
            return TRUE;

        else if($this->getUser()->isInRole('Manažer') && ($role == "Manažer" || $role == "Pracovník" || $role == "Zákazník"))
            return TRUE;

        else if($this->getUser()->isInRole('Pracovník') && ($role == "Pracovník" || $role == "Zákazník"))
            return TRUE;

        else if($this->getUser()->isInRole('Zákazník') && ($role == "Zákazník"))
            return TRUE;
        else
		{
			if($bool == FALSE)
			{
				if ($alert != NULL)
					$this->flashMessage($alert, $type);
				$this->error('Nemáte oprávnění do této sekce', 403);
			} else {
				return FALSE;
			}
		}
    }

      //funkce na převedení form na boostrap 4
  function makeBootstrap4(Nette\Application\UI\Form $form): void
  {
      $renderer = $form->getRenderer();
      $renderer->wrappers['controls']['container'] = null;
      $renderer->wrappers['pair']['container'] = 'div class="form-group row"';
      $renderer->wrappers['pair']['.error'] = 'has-danger';
      $renderer->wrappers['control']['container'] = 'div class=col-sm-9';
      $renderer->wrappers['label']['container'] = 'div class="col-3 col-form-label"';
      $renderer->wrappers['control']['description'] = 'span class=form-text';
      $renderer->wrappers['control']['errorcontainer'] = 'span class=form-control-feedback';
      $renderer->wrappers['control']['.error'] = 'is-invalid';
      foreach ($form->getControls() as $control) {
          $type = $control->getOption('type');
          if ($type === 'button') {
              $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-secondary');
              $usedPrimary = true;
          } elseif (in_array($type, ['text', 'textarea', 'select'], true)) {
              $control->getControlPrototype()->addClass('form-control');
          } elseif ($type === 'file') {
              $control->getControlPrototype()->addClass('form-control-file');
          } elseif (in_array($type, ['checkbox', 'radio'], true)) {
              if ($control instanceof Nette\Forms\Controls\Checkbox) {
                  $control->getLabelPrototype()->addClass('form-check-label');
              } else {
                  $control->getItemLabelPrototype()->addClass('form-check-label');
              }
              $control->getControlPrototype()->addClass('form-check-input');
              $control->getSeparatorPrototype()->setName('div')->addClass('form-check');
          }
      }
  }
}
