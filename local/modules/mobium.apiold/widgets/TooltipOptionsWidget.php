<?php
namespace Mobium\Api\Widgets;


use DigitalWand\AdminHelper\Widget\StringWidget;

class TooltipOptionsWidget extends StringWidget
{

    protected function getEditHtml()
    {
        $this->initScripts();
        //\COption::GetOptionString("main", "server_name", "")
        $sTableId = $this->getEditInputHtmlId().'-table';
        $value = $this->getValue();
        //var_dump($value);
        $sResult = '<table id="'.$sTableId.'">
        <tr>
        <th>Значение</th>
        <th>Цвет текста</th>
        <th>Цвет фона</th>
        <th>Форма</th>
        <th>Показывать в списке</th>
        <th>Показывать на карточке</th>
        <th></th>
</tr>
        ';
        /*
        $sItem = '
        <tr class="line">
        <td><input type="text" name="'.$this->getEditInputName().'[value][]"></td>
        <td><input type="text" name="'.$this->getEditInputName().'[text_color][]"></td>
        <td><input type="text" name="'.$this->getEditInputName().'[background_color][]"></td>
        <td><input type="text" name="'.$this->getEditInputName().'[shape][]"></td>        
        <td><select name="'.$this->getEditInputName().'[show_listing][]"><option value="1">Да</option><option value="0">Нет</option></select></td>
        <td><select name="'.$this->getEditInputName().'[show_card][]"><option value="1">Да</option><option value="0">Нет</option></select></td>
        </tr>
        ';*/
        //$sResult .= $sItem.$sItem;
        $sResult .= '</table>';
        $sResult .= '
        <input type="button" value="Добавить элемент" name="cancel" onclick="window.tooltipOptions.addLine()" title="Добавить элемент">
        <script>
        window.tooltipOptions = new TooltipOptionsTable(\'#'.$sTableId.'\', \''.$this->getEditInputName().'\');
        window.tooltipOptions.setContent('.json_encode($value).');
        
        </script>
        ';
        return $sResult;
    }

    protected function initScripts()
    {
        $path = getLocalPath('modules/mobium.api');
        \CJSCore::RegisterExt('tooltip_options', [
            'js'=>$path.'/js/tooltip-options.js',
            'use'=>\CJSCore::USE_ADMIN,
        ]);
        \CJSCore::Init(['window', 'jquery', 'tooltip_options',]);
    }
}