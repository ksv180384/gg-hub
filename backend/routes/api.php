<?php

use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\GameClassController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GlobalJournalController;
use App\Http\Controllers\Api\GuildApplicationController;
use App\Http\Controllers\Api\GuildApplicationCommentController;
use App\Http\Controllers\Api\GuildApplicationFormFieldController;
use App\Http\Controllers\Api\GuildController;
use App\Http\Controllers\Api\GuildRoleController;
use App\Http\Controllers\Api\GuildPollController;
use App\Http\Controllers\Api\GuildPostController;
use App\Http\Controllers\Api\GuildPostCommentController;
use App\Http\Controllers\Api\LocalizationController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PermissionGroupController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AdminPollController;
use App\Http\Controllers\Api\AdminPostCommentController;
use App\Http\Controllers\Api\AdminGuildApplicationCommentController;
use App\Http\Controllers\Api\AdminPostController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRolePermissionController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventHistoryController;
use App\Http\Controllers\Api\EventHistoryTitleController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\RaidController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json(['message' => 'ok!!!']);
});

Route::get('/admin', function (Request $request) {
    return response()->json(['message' => 'admin']);
});

Route::get('/context', [ContextController::class, 'show']);

Route::get('/games', [GameController::class, 'index']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::get('/games/{game}/localizations/{localization}/servers', [ServerController::class, 'index']);
Route::get('/guilds', [GuildController::class, 'index']);
Route::get('/guilds/{guild}', [GuildController::class, 'show']);
Route::get('/guilds/{guild}/application-form', [GuildController::class, 'applicationForm']);

Route::get('/user', [UserController::class, 'show']);

Route::middleware(['auth'])->group(function () {

    Route::post('/user', [UserController::class, 'update']);

    Route::get('/user/guilds', [UserController::class, 'guilds']);
    Route::get('/user/polls', [UserController::class, 'polls']);
    Route::get('/user/applications', [UserController::class, 'applications']);
    Route::get('/user/posts', [PostController::class, 'index']);
    Route::post('/user/posts', [PostController::class, 'store'])->middleware('ensure.not.banned');
    Route::get('/user/posts/{post}', [PostController::class, 'show']);
    Route::match(['put', 'patch'], '/user/posts/{post}', [PostController::class, 'update'])->middleware('ensure.not.banned');
    Route::get('/games/{game}/journal-posts', [GlobalJournalController::class, 'index']);
    Route::get('/games/{game}/characters', [CharacterController::class, 'indexForGame']);
    Route::get('/games/{game}/characters/{character}', [CharacterController::class, 'showForGame']);
    Route::get('/characters', [CharacterController::class, 'index']);
    Route::get('/characters/{character}', [CharacterController::class, 'show']);
    Route::post('/characters', [CharacterController::class, 'store']);
    Route::match(['put', 'post'], '/characters/{character}', [CharacterController::class, 'update']);
    Route::delete('/characters/{character}', [CharacterController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/guilds/{guild}/events', [EventController::class, 'index'])->middleware('guild.member');
    Route::get('/guilds/{guild}/events/{event}', [EventController::class, 'show'])->middleware('guild.member');
    Route::post('/guilds/{guild}/events', [EventController::class, 'store'])->middleware('guild.member', 'guild.role.permission:dobavliat-sobytie-kalendar');
    Route::match(['put', 'patch'], '/guilds/{guild}/events/{event}', [EventController::class, 'update'])->middleware('guild.member', 'guild.role.permission:redaktirovat-sobytie-kalendar');
    Route::delete('/guilds/{guild}/events/{event}', [EventController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:udaliat-sobytie-kalendar');

    Route::get('/guilds/{guild}/event-history', [EventHistoryController::class, 'index'])->middleware('guild.member');
    Route::get('/guilds/{guild}/event-history/{eventHistory}', [EventHistoryController::class, 'show'])->middleware('guild.member');
    Route::post('/guilds/{guild}/event-history', [EventHistoryController::class, 'store'])->middleware('guild.member', 'guild.role.permission:dobavliat-sobytie');
    Route::match(['put', 'patch'], '/guilds/{guild}/event-history/{eventHistory}', [EventHistoryController::class, 'update'])->middleware('guild.member', 'guild.role.permission:redaktirovat-sobytie');
    Route::delete('/guilds/{guild}/event-history/{eventHistory}', [EventHistoryController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:udaliat-sobytie');

    Route::get('/event-history-titles', [EventHistoryTitleController::class, 'index']);
    Route::match(['put', 'patch'], '/event-history-titles/{eventHistoryTitle}', [EventHistoryTitleController::class, 'update']);
    Route::delete('/event-history-titles/{eventHistoryTitle}', [EventHistoryTitleController::class, 'destroy']);

    Route::get('/guilds/{guild}/raids', [RaidController::class, 'index'])->middleware('guild.member');
    Route::get('/guilds/{guild}/raids/{raid}', [RaidController::class, 'show'])->middleware('guild.member');
    Route::post('/guilds/{guild}/raids', [RaidController::class, 'store'])->middleware('guild.member', 'guild.role.permission:formirovat-reidy');
    Route::match(['put', 'patch'], '/guilds/{guild}/raids/{raid}', [RaidController::class, 'update'])->middleware('guild.member', 'guild.role.permission:formirovat-reidy');
    Route::delete('/guilds/{guild}/raids/{raid}', [RaidController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:udaliat-reidy');
    Route::put('/guilds/{guild}/raids/{raid}/composition', [RaidController::class, 'setComposition'])->middleware('guild.member', 'guild.role.permission:formirovat-reidy');

    Route::get('/guilds/{guild}/roster', [GuildController::class, 'roster']);
    Route::get('/guilds/{guild}/roster/{character}', [GuildController::class, 'showRosterMember']);
    Route::put('/guilds/{guild}/members/{character}/role', [GuildController::class, 'updateMemberRole'])->middleware('guild.member', 'guild.role.permission:meniat-izieniat-polzovateliu-rol');
    Route::delete('/guilds/{guild}/members/{character}', [GuildController::class, 'excludeMember'])->middleware('guild.member', 'guild.role.permission:iskliucenie-polzovatelia-iz-gildii');
    Route::get('/guilds/{guild}/settings', [GuildController::class, 'settings'])->middleware('guild.member');
    Route::get('/guilds/{guild}/polls', [GuildPollController::class, 'index'])->middleware('guild.member');
    Route::get('/guilds/{guild}/polls/{poll}', [GuildPollController::class, 'show'])->middleware('guild.member');
    Route::post('/guilds/{guild}/polls', [GuildPollController::class, 'store'])->middleware('guild.member', 'guild.role.permission:dobavliat-gollosovanie');
    Route::match(['put', 'patch'], '/guilds/{guild}/polls/{poll}', [GuildPollController::class, 'update'])->middleware('guild.member', 'guild.role.permission:redaktirovat-gollosovanie');
    Route::delete('/guilds/{guild}/polls/{poll}', [GuildPollController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:udaliat-gollosovanie');
    Route::post('/guilds/{guild}/polls/{poll}/close', [GuildPollController::class, 'close'])->middleware('guild.member', 'guild.role.permission:zakryvat-gollosovanie');
    Route::post('/guilds/{guild}/polls/{poll}/reset', [GuildPollController::class, 'reset'])->middleware('guild.member', 'guild.role.permission:sbrasyvat-gollosovanie');
    Route::post('/guilds/{guild}/polls/{poll}/vote', [GuildPollController::class, 'vote'])->middleware('guild.member');
    Route::delete('/guilds/{guild}/polls/{poll}/vote', [GuildPollController::class, 'withdrawVote'])->middleware('guild.member');
    Route::get('/guilds/{guild}/posts', [GuildPostController::class, 'index'])->middleware('guild.member');
    Route::get('/guilds/{guild}/posts/pending', [GuildPostController::class, 'pending'])->middleware('guild.member', 'guild.role.permission:publikovat-post');
    Route::get('/guilds/{guild}/posts/{post}', [GuildPostController::class, 'show']);
    Route::get('/guilds/{guild}/posts/{post}/comments', [GuildPostCommentController::class, 'index']);
    Route::post('/guilds/{guild}/posts/{post}/comments', [GuildPostCommentController::class, 'store'])->middleware('guild.member', 'ensure.not.banned');
    Route::match(['put', 'patch'], '/guilds/{guild}/posts/{post}/comments/{comment}', [GuildPostCommentController::class, 'update'])->middleware('guild.member', 'ensure.not.banned');
    Route::delete('/guilds/{guild}/posts/{post}/comments/{comment}', [GuildPostCommentController::class, 'destroy'])->middleware('guild.member');
    Route::post('/guilds/{guild}/posts/{post}/view', [GuildPostController::class, 'recordView']);
    Route::post('/guilds/{guild}/posts/{post}/publish', [GuildPostController::class, 'publish'])->middleware('guild.member', 'guild.role.permission:publikovat-post');
    Route::post('/guilds/{guild}/posts/{post}/reject', [GuildPostController::class, 'reject'])->middleware('guild.member', 'guild.role.permission:publikovat-post');
    Route::post('/guilds/{guild}/posts/{post}/block', [GuildPostController::class, 'block'])->middleware('guild.member', 'guild.role.permission:publikovat-post');
    Route::post('/guilds/{guild}/posts/{post}/unblock', [GuildPostController::class, 'unblock'])->middleware('guild.member', 'guild.role.permission:publikovat-post');
    Route::post('/guilds/{guild}/leave', [GuildController::class, 'leave'])->middleware('guild.member');
    Route::get('/guilds/{guild}/roles', [GuildRoleController::class, 'index'])->middleware('guild.member', 'guild.role.permission:dobavliat-rol,meniat-izieniat-polzovateliu-rol,izmeniat-prava-roli,udaliat-rol');
    Route::get('/guilds/{guild}/permission-groups', [GuildRoleController::class, 'permissionGroups'])->middleware('guild.member', 'guild.role.permission:dobavliat-rol,meniat-izieniat-polzovateliu-rol,izmeniat-prava-roli,udaliat-rol');
    Route::post('/guilds/{guild}/roles', [GuildRoleController::class, 'store'])->middleware('guild.member', 'guild.role.permission:dobavliat-rol');
    Route::put('/guilds/{guild}/roles/{guild_role}/permissions', [GuildRoleController::class, 'updatePermissions'])->middleware('guild.member', 'guild.role.permission:izmeniat-prava-roli');
    Route::delete('/guilds/{guild}/roles/{guild_role}', [GuildRoleController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:udaliat-rol');
    Route::post('/guilds/{guild}/application-form-fields', [GuildApplicationFormFieldController::class, 'store'])->middleware('guild.member', 'guild.role.permission:redaktirovat-formu-zaiavki-v-giliudiiu');
    Route::put('/guilds/{guild}/application-form-fields/{form_field}', [GuildApplicationFormFieldController::class, 'update'])->middleware('guild.member', 'guild.role.permission:redaktirovat-formu-zaiavki-v-giliudiiu');
    Route::delete('/guilds/{guild}/application-form-fields/{form_field}', [GuildApplicationFormFieldController::class, 'destroy'])->middleware('guild.member', 'guild.role.permission:redaktirovat-formu-zaiavki-v-giliudiiu');
    Route::get('/guilds/{guild}/applications', [GuildApplicationController::class, 'index'])->middleware('guild.member', 'guild.role.permission:prosmotr-zaiavok-v-gildiiu');
    Route::get('/guilds/{guild}/applications/{application}', [GuildApplicationController::class, 'show'])->middleware('guild.member', 'guild.role.permission:prosmotr-zaiavok-v-gildiiu');
    // Просмотр заявки пользователем, который её подал
    Route::get('/guilds/{guild}/applications/{application}/owner', [GuildApplicationController::class, 'showForOwner']);
    Route::post('/guilds/{guild}/applications/{application}/withdraw', [GuildApplicationController::class, 'withdraw']);
    Route::get('/guilds/{guild}/applications/{application}/comments', [GuildApplicationCommentController::class, 'index']);
    Route::post('/guilds/{guild}/applications/{application}/comments', [GuildApplicationCommentController::class, 'store'])->middleware('ensure.not.banned');
    Route::match(['put', 'patch'], '/guilds/{guild}/applications/{application}/comments/{comment}', [GuildApplicationCommentController::class, 'update'])->middleware('ensure.not.banned');
    Route::delete('/guilds/{guild}/applications/{application}/comments/{comment}', [GuildApplicationCommentController::class, 'destroy']);
    Route::post('/guilds/{guild}/applications/{application}/accept-invitation', [GuildApplicationController::class, 'acceptInvitation']);
    Route::post('/guilds/{guild}/applications/{application}/decline-invitation', [GuildApplicationController::class, 'declineInvitation']);
    Route::post('/guilds/{guild}/applications/{application}/revoke-invitation', [GuildApplicationController::class, 'revokeInvitation'])->middleware('guild.member', 'guild.role.permission:podtverzdenie-ili-otklonenie-zaiavok');
    Route::post('/guilds/{guild}/applications/{application}/vote', [GuildApplicationController::class, 'vote'])->middleware('guild.member');
    Route::delete('/guilds/{guild}/applications/{application}/vote', [GuildApplicationController::class, 'removeVote'])->middleware('guild.member');
    Route::post('/guilds/{guild}/applications', [GuildApplicationController::class, 'store']);
    Route::post('/guilds/{guild}/invitations', [GuildApplicationController::class, 'invite'])->middleware('guild.member', 'guild.role.permission:podtverzdenie-ili-otklonenie-zaiavok');
    Route::post('/guilds/{guild}/applications/{application}/approve', [GuildApplicationController::class, 'approve'])->middleware('guild.member', 'guild.role.permission:podtverzdenie-ili-otklonenie-zaiavok');
    Route::post('/guilds/{guild}/applications/{application}/reject', [GuildApplicationController::class, 'reject'])->middleware('guild.member', 'guild.role.permission:podtverzdenie-ili-otklonenie-zaiavok');
    Route::post('/guilds', [GuildController::class, 'store']);
    Route::match(['put', 'patch'], '/guilds/{guild}', [GuildController::class, 'update']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);

    Route::middleware(['admin.subdomain', 'permission:admnistrirovanie'])->group(function () {
        Route::get('/admin/posts', [AdminPostController::class, 'index']);
        Route::get('/admin/posts-pending-count', [AdminPostController::class, 'pendingCount']);
        Route::get('/admin/posts/suggest', [AdminPostController::class, 'suggest']);
        Route::get('/admin/posts/{post}', [AdminPostController::class, 'show']);
        Route::post('/admin/posts/{post}/publish', [AdminPostController::class, 'publish'])->middleware('permission:publikovat-post');
        Route::post('/admin/posts/{post}/reject', [AdminPostController::class, 'reject'])->middleware('permission:publikovat-post');
        Route::post('/admin/posts/{post}/block', [AdminPostController::class, 'block'])->middleware('permission:blokirovat-posty');
        Route::post('/admin/posts/{post}/hide', [AdminPostController::class, 'hide'])->middleware('permission:blokirovat-posty');
        Route::post('/admin/posts/{post}/unblock', [AdminPostController::class, 'unblock'])->middleware('permission:blokirovat-posty');
        Route::get('/admin/polls', [AdminPollController::class, 'index'])->middleware('permission:admnistrirovanie,prosmatirivat-golosovaniia');
        Route::delete('/admin/polls/{poll}', [AdminPollController::class, 'destroy'])->middleware('permission:udaliat-golosovanie');
        Route::get('/admin/comments', [AdminPostCommentController::class, 'index']);
        Route::post('/admin/comments/{comment}/hide', [AdminPostCommentController::class, 'hide'])->middleware('permission:skryvat-kommentarii');
        Route::post('/admin/comments/{comment}/unhide', [AdminPostCommentController::class, 'unhide'])->middleware('permission:skryvat-kommentarii');
        Route::delete('/admin/comments/{comment}', [AdminPostCommentController::class, 'destroy'])->middleware('permission:udaliat-kommentarii');
        Route::get('/admin/application-comments', [AdminGuildApplicationCommentController::class, 'index']);
        Route::post('/admin/application-comments/{comment}/hide', [AdminGuildApplicationCommentController::class, 'hide'])->middleware('permission:skryvat-kommentarii');
        Route::post('/admin/application-comments/{comment}/unhide', [AdminGuildApplicationCommentController::class, 'unhide'])->middleware('permission:skryvat-kommentarii');
        Route::delete('/admin/application-comments/{comment}', [AdminGuildApplicationCommentController::class, 'destroy'])->middleware('permission:udaliat-kommentarii');
        Route::get('/permission-groups', [PermissionGroupController::class, 'index']);
        Route::get('/permission-groups/{permission_group}', [PermissionGroupController::class, 'show']);
        Route::post('/permission-groups', [PermissionGroupController::class, 'store'])->middleware('permission:obshhie-roli');
        Route::put('/permission-groups/{permission_group}', [PermissionGroupController::class, 'update'])->middleware('permission:obshhie-roli');
        Route::delete('/permission-groups/{permission_group}', [PermissionGroupController::class, 'destroy'])->middleware('permission:obshhie-roli');
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:obshhie-roli');
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:obshhie-roli');
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:zablokirovat-polzovatelia');
        Route::put('/users/{user}/roles-permissions', [UserRolePermissionController::class, 'update'])->middleware('permission.roles-permissions');

        Route::post('/games', [GameController::class, 'store'])->middleware('permission:dobavliat-igru');
        Route::post('/games/{game}', [GameController::class, 'update'])->middleware('permission:redaktirovat-igru');
        Route::delete('/games/{game}', [GameController::class, 'destroy'])->middleware('permission:udaliat-igru');
        Route::get('/games/{game}/game-classes', [GameClassController::class, 'index'])->middleware('permission:redaktirovat-igru');
        Route::post('/games/{game}/game-classes', [GameClassController::class, 'store'])->middleware('permission:redaktirovat-igru');
        Route::match(['put', 'post'], '/game-classes/{game_class}', [GameClassController::class, 'update'])->middleware('permission:redaktirovat-igru');
        Route::delete('/game-classes/{game_class}', [GameClassController::class, 'destroy'])->middleware('permission:redaktirovat-igru');
        Route::post('/games/{game}/localizations', [LocalizationController::class, 'store'])->middleware('permission:dobavliat-lokalizaciia');

//        Route::get('/games/{game}/localizations/{localization}/servers', [ServerController::class, 'index'])->middleware('permission:dobaliat-server');
        Route::post('/games/{game}/localizations/{localization}/servers', [ServerController::class, 'store'])->middleware('permission:dobaliat-server');
        Route::put('/servers/{server}', [ServerController::class, 'update'])->middleware('permission:redaktirovat-server');
        Route::delete('/servers/{server}', [ServerController::class, 'destroy'])->middleware('permission:udaliat-server');

        Route::post('/games/{game}/localizations/{localization}/servers/merge', [ServerController::class, 'merge'])->middleware('permission:obieediniat-servera');

        Route::put('/tags/{tag}', [TagController::class, 'update']);
        Route::delete('/tags/{tag}', [TagController::class, 'destroy']);
    });
});
