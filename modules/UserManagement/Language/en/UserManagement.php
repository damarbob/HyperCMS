<?php

return [
    "moduleName" => "Users",
    "moduleDescription" => "Manage user identities and privileges",

    "userSavedSuccessfully" => "User saved successfully",
    "userDeletedSuccessfully" => "User deleted successfully",

    'userForm' => 'User Form',
    'leaveBlankToKeepCurrentPassword' => 'Leave blank to keep the current password',
    'groups' => 'Groups',
    'userNotFound'          => 'That person does not exist.',
    'notAllowedToHandle'    => 'You may not change this account, because it has a role above yours.',
    'notAllowedToAssign'    => 'You may not give or take the role {role}, because it can do more than your own roles.',
    'lastSuperadmin'        => 'This is the last Super Admin, so the role cannot be taken away or the account deleted.',
    'cannotDeleteYourself'  => 'You cannot delete your own account.',
];
