<?php

return [
    'unsupportedType'  => 'The field {field} has the type {type}, which cannot be marked filterable. Remove "filterable" from it, or use one of these types: {types}.',
    'notSingleValue'   => 'The field {field} is filterable and holds one value, so it cannot take a list.',
    'tooLong'          => 'The field {field} is filterable, so its text may be at most {max} characters long.',
    'notNumber'        => 'The field {field} is filterable and takes a number, but "{value}" is not one. Enter a number or leave it empty.',
    'notInteger'       => 'The field {field} is filterable and takes a whole number, but "{value}" is not one. Enter a whole number or leave it empty.',
    'notDate'          => 'The field {field} is filterable and takes a date and time, but "{value}" is not one. Pick a date and time or leave it empty.',
    'indexingFailed'   => 'The model was saved, but indexing could not start for {fields}. Save the model again to retry. {reason}',
    'indexed'          => 'Indexed',
    'indexing'         => 'Indexing',
    'indexingHelp'     => 'Indexing runs in the background and needs the scheduled task `hyper:tick`. Sorting and filtering on the field start working when it is done.',
    'filterableTitle'  => 'Filterable fields',
    'filterableHint'   => 'Add "filterable": true to a field of the type text, email, url, password, color, radio, select, checkbox, number, range or datetime-local to index it, so lists can sort on it quickly and the token API can filter on it. Leave it out and the field works as before.',
    'modelsBusy'       => '{count} model(s) stayed in the trash because a field is still being indexed or renamed. Try again after `hyper:tick` has finished.',
];
