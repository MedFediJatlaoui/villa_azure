<?php

namespace IAWP;

/** @internal */
class BreakdanceFormAction
{
    public static function register()
    {
        if (!\function_exists('\\Breakdance\\Forms\\Actions\\registerAction') || !\class_exists('\\Breakdance\\Forms\\Actions\\Action')) {
            return;
        }
        // https://github.com/soflyy/breakdance-developer-docs/blob/master/form-actions/readme.md
        $action = new class extends \Breakdance\Forms\Actions\Action
        {
            public static function name()
            {
                return 'Independent Analytics Pro';
            }
            public static function slug()
            {
                return 'iawp_record_submission';
            }
            public function run($form, $settings, $extra)
            {
                try {
                    $form_id = $extra['formId'];
                    $form_name = $settings['form']['form_name'];
                    \do_action('iawp_breakdance_form_submission', $form_id, $form_name);
                } catch (\Throwable $error) {
                }
                return ['type' => 'success', 'message' => 'Form submission recorded'];
            }
        };
        \Breakdance\Forms\Actions\registerAction($action);
    }
}
