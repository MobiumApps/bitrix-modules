function TooltipOptionsTable(sTableSelector, sFieldNamePerfix){
    var self = this;
    self.$table = $(sTableSelector);
    self.fieldNamePrefix = sFieldNamePerfix;


}

TooltipOptionsTable.prototype.addLine = function (values) {
    console.log(values);
    var self = this;
    var template = '<tr class="line">' +
        '<td class="value"><input type="text" name="'+self.fieldNamePrefix+'[value][]"></td>'+
        '<td class="text_color"><input type="text" name="'+self.fieldNamePrefix+'[text_color][]" value="#ffffff"></td>'+
        '<td class="background_color"><input type="text" name="'+self.fieldNamePrefix+'[background_color][]" value="#000000"></td>'+
        '<td class="shape"><input type="text" name="'+self.fieldNamePrefix+'[shape][]" value="rectangle"></td>'+
        '<td class="show_listing"><select name="'+self.fieldNamePrefix+'[show_listing][]"><option value="1">Да</option><option value="0">Нет</option></select></td>'+
        '<td class="show_card"><select name="'+self.fieldNamePrefix+'[show_card][]"><option value="1">Да</option><option value="0">Нет</option></select></td>'+
        '<td><span style="cursor:pointer" title="Удалить строку" onclick="window.tooltipOptions.removeLine(event)">X</span></td>'+
        '</tr>';
    self.$table.find('tbody').append(template);
    if (values){
        var $lastLine = self.$table.find('tbody tr.line:last'), t;
        for (var key in values){
            t = 'input';
            if (key == 'show_listing' || key == 'show_card'){
                t = 'select';
            }
            $lastLine.find('td.'+key+' '+t).val(values[key]);
        }
    }

};

TooltipOptionsTable.prototype.removeLine = function (event) {
    var self = this;
    var $target = $(event.target);
    $target.parent().parent().remove();
};

TooltipOptionsTable.prototype.setContent = function (values) {
    var self = this;
    var result = [], i;
    for (var key in values){
        for (i=0; i<values[key].length; ++i){
            if (result.length < i+1){
                result.push({});
            }
            result[i][key] = values[key][i];
        }
    }
    console.log(result);
    for (i=0; i<result.length; ++i){
        self.addLine(result[i]);
    }
};