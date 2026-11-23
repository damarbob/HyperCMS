<?php

// Messages of the media library storage.
return [
    'rootMissing'       => 'No upload folder is set. Set media.uploadPath in the environment file.',
    'rootCannotBeMade'  => 'The upload folder {path} does not exist and could not be made.',
    'rootIsPublic'      => 'The upload folder is inside the public folder, so the web server would hand out the files itself. Choose a folder outside it.',
    'folderTooDeep'     => 'Folders can go only eight levels deep.',
    'badFolderName'     => 'The folder name "{name}" cannot be used.',
    'badFileName'       => 'The file name "{name}" cannot be used.',
    'fileNameRefused'   => 'Files named "{name}" are not accepted, because a web server could run them as code.',
    'folderNotFound'    => 'The folder "{name}" does not exist.',
    'folderNotEmpty'    => 'The folder "{name}" is not empty. Move or delete what is in it first.',
    'sourceMissing'     => 'The file "{name}" was not received.',
    'cannotWrite'       => 'The file "{name}" could not be written to the upload folder.',
    'fileNotFound'      => 'That file is not in the library.',
    'nameTaken'         => 'There is already something called "{name}" there.',
    'fileMissingOnDisk' => 'The file "{name}" is in the library but not on the disk.',
];
