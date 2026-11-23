<?php

return [
    'moduleName' => 'Data Comparison',
    'moduleDescription' => 'Compare data across different sources and formats.',
    'moduleVersion' => '1.0.0',

    'egx' => 'e.g. {x}',

    'state' => 'State',
    'loadState' => 'Load state',
    'deleteState' => 'Delete state',
    'saveState' => 'Save state',
    'lastUsedState' => 'Last used state',

    'dataSource' => 'Data Source',
    'dataSources' => 'Data Sources',
    'entryx' => 'Entry {x}',
    'fieldId' => 'Field ID',
    'value' => 'Value',

    'addEntry' => 'Add entry',
    'editEntry' => 'Edit entry',
    'addField' => 'Add field',
    'editField' => 'Edit field',

    'failedToFetchEntries' => 'Failed to fetch entries!',
    'failedToFetchEntryData' => 'Failed to fetch entry data!',
    'failedToFetchStates' => 'Failed to fetch states from server. Using local saved states.',
    'failedToFetchLastUsedState' => 'Failed to fetch last used state from server.',
    'failedToSaveLastUsedState' => 'Failed to save last used state to server.',

    'entryFromExternalDataSource' => "The entry is from an external data source!",
    'pleaseFinishCurrentEditFirst' => 'Please finish current edit first',

    'pleaseAddEntryFirst' => 'Please add an entry first.',
    'pleaseFillAllRequiredFields' => 'Please fill all required fields.',
    'columnWithSameIdAlreadyExists' => 'A column with the same ID already exists.',
    'pleaseEnterBothFormulaLabel' => 'Please enter both formula and label.',
    'pleaseSelectField' => 'Please select a field.',
    'pleaseProvideFormula' => 'Please provide a formula.',
    'pleaseProvideBothFieldLabelAndId' => 'Please provide both field label and ID.',

    'fieldxDoesNotExist' => 'Field {x} does not exist.',
    'fieldxAlreadyExists' => 'Field {x} already exists.',

    'dragToReorderEntry' => 'Drag to reorder entry',
    'dragToReorderField' => 'Drag to reorder field',

    'selectModel' => 'Select model',
    'chooseModel' => 'Choose a model',
    'selectAll' => 'Select all',
    'selectEntry' => 'Select entry',
    'chooseEntry' => 'Choose an entry',

    'previewEntry' => 'Preview entry',
    'contentHere' => 'Content here...',

    'requiredUnlessUseExternalDataSource' => 'Required unless you use external data source.',
    'optionalUnlessUseExternalDataSource' => 'Optional unless you use external data source.',
    'optionalLabelForDisplayPurposes' => 'Optional label for display purposes.',

    'field' => 'Field',
    'fieldType' => 'Field type',
    'chooseFieldType' => 'Choose a field type',
    'customField' => 'Custom field',
    'mathFormula' => 'Math formula',
    'selectField' => 'Select field',
    'chooseField' => 'Choose a field',
    'fieldFormula' => 'Field formula',
    'formulaLabel' => 'Formula label',
    'customFieldLabel' => 'Custom field label',
    'customFieldId' => 'Custom field ID',
    'formulaValue' => 'Formula value',
    'myCustomField' => 'My Custom Field',
    'my_custom_field' => 'my_custom_field',

    'pleaseSelectFieldType' => "Please select a field type.",

    'selectState' => 'Select a state',

    'enterNameForState' => 'Enter a name for the state',
    'statexAlreadyExistsOverwrite' => 'State {x} already exists. Do you want to overwrite it?',
    'statesSavedSuccessfully' => 'States saved successfully.',
    'statexSavedSuccessfully' => 'State {x} saved successfully.',
    'youHaveUnsavedChangesLoadAnyway' => 'You have unsaved changes. Do you want to load anyway?',
    'deleteStatex' => 'Delete state {x}?',
    'statexDeletedSuccessfully' => 'State {x} deleted successfully.',

    'failedToSaveStates' => 'Failed to save states to server.',

    'entryUpdatedSuccessfully' => 'Entry updated successfully!',

    // Settings
    'enterDataSourcesInJsonFormat' => 'Enter the data sources in JSON format.',
    'invalidJsonFormatForStates' => 'Invalid JSON format for states.',

    // The rebuilt screen
    'unsavedChanges' => 'Unsaved changes',
    'saveStateAs' => 'Save as',
    'undo' => 'Undo',
    'redo' => 'Redo',
    'emptyTitle' => 'Nothing to compare yet',
    'emptyText' => 'Add an entry to make a column, and a field to make a row.',
    'removeEntry' => 'Remove this entry from the table',
    'removeField' => 'Remove this field from the table',
    'entryNumber' => 'Entry number',
    'entryLabel' => 'Column title',
    'fieldsToAdd' => 'Fields to add as rows',
    'formulaHelp' => 'Refer to a cell in square brackets. [price] is the field price of the same column, [e12:price] is the same field of entry 12, and [m3:price] is of model 3. A cell that is not a number counts as 0.',
    'formulaError' => 'Error',
    'cellCannotBeWritten' => 'This field cannot be changed from the table.',
    'dataSourcesText' => 'Where the table can fetch entries from. This site is already there. Add another site by giving its address.',
    'dataSourcesMustBeAList' => 'The data sources must be a list, written as JSON.',
    'dataSourceNeedsFields' => 'Each data source needs its own id, a label and an address in options.url.',
];
