<?php

return array (
  'backend' => 
  array (
    'access' => 
    array (
      'roles' => 
      array (
        'already_exists' => 'Ese papel ya existe. Por favor, elija un nombre diferente.',
        'cant_delete_admin' => 'No se puede eliminar la función de administrador.',
        'create_error' => 'Hubo un problema creando este rol. Inténtalo de nuevo.',
        'delete_error' => 'Hubo un problema al eliminar este rol. Inténtalo de nuevo.',
        'has_users' => 'No se puede eliminar un rol con usuarios asociados.',
        'needs_permission' => 'Debe seleccionar al menos un permiso para este rol.',
        'not_found' => 'Ese papel no existe.',
        'update_error' => 'Hubo un problema al actualizar este rol. Inténtalo de nuevo.',
      ),
      'users' => 
      array (
        'already_confirmed' => 'Este usuario ya está confirmado.',
        'cant_confirm' => 'Hubo un problema al confirmar la cuenta de usuario.',
        'cant_deactivate_self' => 'No puedes hacer eso a ti mismo.',
        'cant_delete_admin' => 'No se puede eliminar el super administrador.',
        'cant_delete_self' => 'No puedes borrarte.',
        'cant_delete_own_session' => 'No puedes borrar tu propia sesión.',
        'cant_restore' => 'Este usuario no se elimina, por lo que no se puede restaurar.',
        'cant_unconfirm_admin' => 'No puedes anular la confirmación del super administrador.',
        'cant_unconfirm_self' => 'Usted no puede anular la confirmación de sí mismo.',
        'create_error' => 'Hubo un problema al crear este usuario. Inténtalo de nuevo.',
        'delete_error' => 'Hubo un problema al eliminar este usuario. Inténtalo de nuevo.',
        'delete_first' => 'Este usuario debe eliminarse primero antes de que pueda ser destruido permanentemente.',
        'email_error' => 'Esa dirección de correo electrónico pertenece a un usuario diferente.',
        'mark_error' => 'Hubo un problema al actualizar este usuario. Inténtalo de nuevo.',
        'not_confirmed' => 'Este usuario no está confirmado.',
        'not_found' => 'Ese usuario no existe.',
        'restore_error' => 'Hubo un problema al restaurar este usuario. Inténtalo de nuevo.',
        'role_needed_create' => 'Debes elegir al menos un rol.',
        'role_needed' => 'Debes elegir al menos un rol.',
        'session_wrong_driver' => 'Su controlador de sesión debe configurarse en la base de datos para usar esta función.',
        'social_delete_error' => 'Hubo un problema al eliminar la cuenta social del usuario.',
        'update_error' => 'Hubo un problema al actualizar este usuario. Inténtalo de nuevo.',
        'update_password_error' => 'Hubo un problema al cambiar la contraseña de este usuario. Inténtalo de nuevo.',
      ),
    ),
  ),
  'frontend' => 
  array (
    'auth' => 
    array (
      'confirmation' => 
      array (
        'already_confirmed' => 'Tu cuenta ya está confirmada.',
        'confirm' => '¡Confirme su cuenta!',
        'created_confirm' => 'Tu cuenta fue creada exitosamente. Le hemos enviado un correo electrónico para confirmar su cuenta.',
        'created_pending' => 'Su cuenta fue creada exitosamente y está pendiente de aprobación. Se enviará un correo electrónico cuando se apruebe su cuenta.',
        'mismatch' => 'Su código de confirmación no coincide.',
        'not_found' => 'Ese código de confirmación no existe.',
        'pending' => 'Su cuenta está pendiente de aprobación.',
        'resend' => 'Tu cuenta no está confirmada. Haga clic en el enlace de confirmación en su correo electrónico o <a href=":url">click haga clic aquí </a> para reenviar el correo electrónico de confirmación.',
        'success' => 'Su cuenta ha sido confirmada con éxito!',
        'resent' => 'Se ha enviado un nuevo correo electrónico de confirmación a la dirección que figura en el archivo.',
      ),
      'deactivated' => 'Tu cuenta ha sido desactivada.',
      'email_taken' => 'Esa dirección de correo electrónico ya está tomada.',
      'password' => 
      array (
        'change_mismatch' => 'Esa no es tu antigua contraseña.',
        'reset_problem' => 'Hubo un problema al restablecer su contraseña. Por favor, vuelva a enviar el correo electrónico de restablecimiento de contraseña.',
      ),
      'registration_disabled' => 'La inscripción está actualmente cerrada.',
    ),
  ),
);
