<?php

return [
    'unsupportedType'  => 'The field {field} has the type {type}, which cannot be marked filterable. Remove "filterable" from it, or use one of these types: {types}.',
    'notSingleValue'   => 'The field {field} is filterable and holds one value, so it cannot take a list.',
    'tooLong'          => 'The field {field} is filterable, so its text may be at most {max} characters long.',
    'notNumber'        => 'The field {field} is filterable and takes a number, but "{value}" is not one. Enter a number or leave it empty.',
    'notInteger'       => 'The field {field} is filterable and takes a whole number, but "{value}" is not one. Enter a whole number or leave it empty.',
    'notDate'          => 'The field {field} is filterable and takes a date and time, but "{value}" is not one. Pick a date and time or leave it empty.',
];
