{"version":3,"sources":["calendar-section.js"],"names":["window","SectionController","calendar","data","config","sections","this","sectionIndex","hiddenSections","prepareData","showTasks","taskSection","TaskSection","sectionCustomization","tasks","push","id","length","BX","addCustomEvent","proxy","unsetSectionHandler","sortSections","prototype","params","i","section","Section","sort","a","b","type","isFunction","isPseudo","name","localeCompare","getCurrentSection","lastUsed","util","getUserOption","getSection","canDo","belongsToView","isActive","isExternalMode","getSectionList","result","parseInt","getSuperposedSectionList","isSuperposed","getSectionListForEdit","getDefaultSectionName","message","getDefaultSectionColor","sectionList","usedColors","color","defaultColors","getDefaultColors","randomInt","getDefaultSectionAccess","new_section_access","saveSection","access","promise","Promise","trim","onCustomEvent","isCustomization","ajax","runAction","analyticsLabel","action","ownerId","userId","customization","then","delegate","response","toLowerCase","reload","updateData","NAME","COLOR","fulfill","displayError","errors","sectionIsShown","in_array","getHiddenSections","setHiddenSections","getSectionsInfo","allActive","superposed","active","hidden","isShown","sectionId","undefined","deleteFromArray","CAL_TYPE","Object","defineProperties","value","ID","writable","enumerable","sectionController","show","array_search","userOptions","save","hide","remove","confirm","hideGoogle","getLink","LINK","canBeConnectedToOutlook","OUTLOOK_JS","CAL_DAV_CAL","CAL_DAV_CON","browser","IsMac","connectToOutlook","jsOutlookUtils","loadScript","eval","e","isVirtual","PERM","SUPERPOSED","indexOf","GAPI_CALENDAR_ID","isGoogle","isCalDav","isCompanyCalendar","OWNER_ID","belongsToOwner","belongsToUser","ACTIVE","defaultColor","defaultName","userIsOwner","isUserCalendar","isGroupCalendar","edit_section","view_full","view_time","view_title","apply","create","constructor","BXEventCalendar"],"mappings":"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","file":"calendar-section.map.js"}