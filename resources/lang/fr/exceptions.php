<?php

return array (
  'backend' => 
  array (
    'access' => 
    array (
      'roles' => 
      array (
        'already_exists' => 'Ce rôle existe déjà. Veuillez choisir un autre nom.',
        'cant_delete_admin' => 'Vous ne pouvez pas supprimer le rôle d\'administrateur.',
        'create_error' => 'Un problème est survenu lors de la création de ce rôle. Veuillez réessayer.',
        'delete_error' => 'Un problème est survenu lors de la suppression de ce rôle. Veuillez réessayer.',
        'has_users' => 'Vous ne pouvez pas supprimer un rôle avec des utilisateurs associés.',
        'needs_permission' => 'Vous devez sélectionner au moins une autorisation pour ce rôle.',
        'not_found' => 'Ce rôle n\'existe pas.',
        'update_error' => 'Un problème est survenu lors de la mise à jour de ce rôle. Veuillez réessayer.',
      ),
      'users' => 
      array (
        'already_confirmed' => 'Cet utilisateur est déjà confirmé.',
        'cant_confirm' => 'Un problème est survenu lors de la confirmation du compte d\'utilisateur.',
        'cant_deactivate_self' => 'Vous ne pouvez pas faire ça pour vous.',
        'cant_delete_admin' => 'Vous ne pouvez pas supprimer le super administrateur.',
        'cant_delete_self' => 'Vous ne pouvez pas vous supprimer.',
        'cant_delete_own_session' => 'Vous ne pouvez pas supprimer votre propre session.',
        'cant_restore' => 'Cet utilisateur n\'est pas supprimé et ne peut donc pas être restauré.',
        'cant_unconfirm_admin' => 'Vous ne pouvez pas annuler la confirmation du super administrateur.',
        'cant_unconfirm_self' => 'Vous ne pouvez pas annuler votre confirmation.',
        'create_error' => 'Un problème est survenu lors de la création de cet utilisateur. Veuillez réessayer.',
        'delete_error' => 'Un problème est survenu lors de la suppression de cet utilisateur. Veuillez réessayer.',
        'delete_first' => 'Cet utilisateur doit d\'abord être supprimé pour pouvoir être détruit définitivement.',
        'email_error' => 'Cette adresse email appartient à un autre utilisateur.',
        'mark_error' => 'Un problème est survenu lors de la mise à jour de cet utilisateur. Veuillez réessayer.',
        'not_confirmed' => 'Cet utilisateur n\'est pas confirmé.',
        'not_found' => 'Cet utilisateur n\'existe pas.',
        'restore_error' => 'Un problème est survenu lors de la restauration de cet utilisateur. Veuillez réessayer.',
        'role_needed_create' => 'Vous devez choisir au moins un rôle.',
        'role_needed' => 'Vous devez choisir au moins un rôle.',
        'session_wrong_driver' => 'Votre pilote de session doit être défini sur la base de données pour utiliser cette fonctionnalité.',
        'social_delete_error' => 'Un problème est survenu lors de la suppression du compte social de l\'utilisateur.',
        'update_error' => 'Un problème est survenu lors de la mise à jour de cet utilisateur. Veuillez réessayer.',
        'update_password_error' => 'Un problème est survenu lors de la modification du mot de passe de cet utilisateur. Veuillez réessayer.',
      ),
    ),
  ),
  'frontend' => 
  array (
    'auth' => 
    array (
      'confirmation' => 
      array (
        'already_confirmed' => 'Votre compte est déjà confirmé.',
        'confirm' => 'Confirmez votre compte!',
        'created_confirm' => 'Votre compte a été créé avec succès. Nous vous avons envoyé un e-mail pour confirmer votre compte.',
        'created_pending' => 'Votre compte a été créé avec succès et est en attente d\'approbation. Un e-mail sera envoyé lorsque votre compte sera approuvé.',
        'mismatch' => 'Votre code de confirmation ne correspond pas.',
        'not_found' => 'Ce code de confirmation n\'existe pas.',
        'pending' => 'Votre compte est actuellement en attente d\'approbation.',
        'resend' => 'Votre compte n\'est pas confirmé. Veuillez cliquer sur le lien de confirmation dans votre courrier électronique ou <a href=":url">click cliquer ici </a> pour renvoyer le message de confirmation.',
        'success' => 'Votre compte a été confirmé avec succès!',
        'resent' => 'Un nouvel e-mail de confirmation a été envoyé à l\'adresse enregistrée.',
      ),
      'deactivated' => 'Votre compte a été désactivé.',
      'email_taken' => 'Cette adresse e-mail est déjà prise.',
      'password' => 
      array (
        'change_mismatch' => 'Ce n\'est pas votre ancien mot de passe.',
        'reset_problem' => 'Un problème est survenu lors de la réinitialisation de votre mot de passe. Veuillez renvoyer l\'e-mail de réinitialisation du mot de passe.',
      ),
      'registration_disabled' => 'L\'inscription est actuellement fermée.',
    ),
  ),
);
