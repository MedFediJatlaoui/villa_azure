<?php

namespace IAWP\AJAX;

use IAWP\Overview\Modules\Module;
/** @internal */
class Get_Markup_For_Modules extends \IAWP\AJAX\AJAX
{
    /**
     * @inheritDoc
     */
    protected function action_name() : string
    {
        return 'iawp_get_markup_for_modules';
    }
    /**
     * @inheritDoc
     */
    protected function action_required_fields() : array
    {
        return ['ids'];
    }
    protected function requires_pro() : bool
    {
        return \true;
    }
    /**
     * @inheritDoc
     */
    protected function action_callback() : void
    {
        $ids = $this->get_array_field('ids');
        $modules = [];
        foreach ($ids as $id) {
            $module = Module::get_saved_module($id);
            if ($module) {
                $modules[] = ['id' => $id, 'editorHtml' => $module->get_editor_html(), 'moduleHtml' => $module->get_module_html(), 'hasDataset' => $module->has_dataset()];
            }
        }
        \wp_send_json_success(['modules' => $modules, 'modulesRefreshedAt' => Module::last_refreshed_at()]);
    }
}
