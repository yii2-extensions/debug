<?php

declare(strict_types=1);

namespace yii\debug\exception;

use function sprintf;

/**
 * Defines exception message templates used by the Yii debug adapter.
 *
 * Use {@see Message::getMessage()} to format a template with `sprintf()` arguments.
 */
enum Message: string
{
    /**
     * Access to the requested debug page is denied.
     *
     * Format: "You are not allowed to access this page."
     */
    case ACCESS_DENIED = 'You are not allowed to access this page.';

    /**
     * Access to the debugger is denied by the configured callback.
     *
     * Format: "Access to debugger is denied due to checkAccessCallback."
     */
    case ACCESS_DENIED_BY_CALLBACK = 'Access to debugger is denied due to checkAccessCallback.';

    /**
     * An active session is required.
     *
     * Format: "Need an active session"
     */
    case ACTIVE_SESSION_REQUIRED = 'Need an active session';

    /**
     * The mode cannot be applied to a captured mail file.
     *
     * Format: "Unable to apply mode to captured mail file: %s"
     */
    case CAPTURED_MAIL_FILE_MODE_FAILED = 'Unable to apply mode to captured mail file: %s';

    /**
     * A captured mail file name is invalid.
     *
     * Format: "Invalid captured mail file name: %s"
     */
    case CAPTURED_MAIL_FILE_NAME_INVALID = 'Invalid captured mail file name: %s';

    /**
     * A captured mail file cannot be persisted.
     *
     * Format: "Unable to persist captured mail file: %s"
     */
    case CAPTURED_MAIL_FILE_PERSIST_FAILED = 'Unable to persist captured mail file: %s';

    /**
     * The collector configuration lacks a valid class name.
     *
     * Format: "Debug collector configuration must declare a valid class name."
     */
    case COLLECTOR_CLASS_INVALID = 'Debug collector configuration must declare a valid class name.';

    /**
     * A collector entry declares a non-boolean `enabled` flag.
     *
     * Format: "Debug collector '%s' must declare 'enabled' as a boolean."
     */
    case COLLECTOR_ENABLED_INVALID = "Debug collector '%s' must declare 'enabled' as a boolean.";

    /**
     * The configured collector does not implement the required interface.
     *
     * Format: "Debug collector class must implement %s: %s."
     */
    case COLLECTOR_INTERFACE_INVALID = 'Debug collector class must implement %s: %s.';

    /**
     * The debug collectors have not been initialized.
     *
     * Format: "Debug collectors have not been initialized."
     */
    case COLLECTORS_NOT_INITIALIZED = 'Debug collectors have not been initialized.';

    /**
     * CSRF validation has failed.
     *
     * Format: "Unable to verify your data submission."
     */
    case CSRF_VALIDATION_FAILED = 'Unable to verify your data submission.';

    /**
     * An application component is not a database connection.
     *
     * Format: "Application component '%s' must be a DB connection."
     */
    case DB_COMPONENT_INVALID = "Application component '%s' must be a DB connection.";

    /**
     * A debug action is dispatched through an incompatible module.
     *
     * Format: "Debug actions must be dispatched through the debug module."
     */
    case DEBUG_ACTION_MODULE_INVALID = 'Debug actions must be dispatched through the debug module.';

    /**
     * No debug data have been collected.
     *
     * Format: "No debug data have been collected yet, try browsing the website first."
     */
    case DEBUG_DATA_EMPTY = 'No debug data have been collected yet, try browsing the website first.';

    /**
     * Debug data cannot be found for a tag.
     *
     * Format: "Unable to find debug data tagged with '%s'."
     */
    case DEBUG_DATA_NOT_FOUND = "Unable to find debug data tagged with '%s'.";

    /**
     * Tagged debug data omit the summary payload.
     *
     * Format: "Debug data tagged with '%s' does not contain summary data."
     */
    case DEBUG_DATA_SUMMARY_MISSING = "Debug data tagged with '%s' does not contain summary data.";

    /**
     * A summary is unavailable for tagged debug data.
     *
     * Format: "Debug data tagged with '%s' has no summary."
     */
    case DEBUG_DATA_SUMMARY_UNAVAILABLE = "Debug data tagged with '%s' has no summary.";

    /**
     * A requested debug panel cannot be found.
     *
     * Format: "Debug panel '%s' not found."
     */
    case DEBUG_PANEL_NOT_FOUND = "Debug panel '%s' not found.";

    /**
     * The requested capture tag cannot be resolved.
     *
     * Format: "Debug tag not found."
     */
    case DEBUG_TAG_NOT_FOUND = 'Debug tag not found.';

    /**
     * A `dispatchers` entry names a collector that cannot act as a PSR-14 dispatcher.
     *
     * Format: "Debug collector '%s' must implement %s to be handed to a component as its dispatcher."
     */
    case DISPATCHER_COLLECTOR_NOT_DISPATCHER
        = "Debug collector '%s' must implement %s to be handed to a component as its dispatcher.";

    /**
     * A `dispatchers` entry names a collector that is neither registered nor disabled.
     *
     * Format: "Debug dispatcher '%s' names no configured collector."
     */
    case DISPATCHER_COLLECTOR_UNKNOWN = "Debug dispatcher '%s' names no configured collector.";

    /**
     * A `dispatchers` entry targets a component that is already built and has no writable dispatcher property.
     *
     * Format: "Application component '%s' is already instantiated and exposes no writable 'eventDispatcher' property."
     */
    case DISPATCHER_COMPONENT_INSTANTIATED
        = "Application component '%s' is already instantiated and exposes no writable 'eventDispatcher' property.";

    /**
     * A `dispatchers` entry targets a component the application does not declare.
     *
     * Format: "Debug dispatcher '%s' targets the unknown application component '%s'."
     */
    case DISPATCHER_COMPONENT_UNKNOWN = "Debug dispatcher '%s' targets the unknown application component '%s'.";

    /**
     * A `dispatchers` entry targets a component definition that cannot take the collector.
     *
     * Format: "Application component '%s' must be a class name or configuration array declaring an 'eventDispatcher'
     * property or constructor parameter."
     */
    case DISPATCHER_TARGET_UNSUPPORTED
        = "Application component '%s' must be a class name or configuration array declaring an 'eventDispatcher' "
        . 'property or constructor parameter.';

    /**
     * The user component lacks an identity class.
     *
     * Format: "User component is not configured with an identity class."
     */
    case IDENTITY_CLASS_NOT_CONFIGURED = 'User component is not configured with an identity class.';

    /**
     * The requested identity cannot be found.
     *
     * Format: "Identity not found."
     */
    case IDENTITY_NOT_FOUND = 'Identity not found.';

    /**
     * User switching requires an attached identity.
     *
     * Format: "Cannot switch to a user without an attached identity."
     */
    case IDENTITY_REQUIRED_FOR_SWITCH = 'Cannot switch to a user without an attached identity.';

    /**
     * A requested log message cannot be found.
     *
     * Format: "Log message not found."
     */
    case LOG_MESSAGE_NOT_FOUND = 'Log message not found.';

    /**
     * The log target configuration lacks a valid class name.
     *
     * Format: "Debug module logTarget configuration must declare a valid class name."
     */
    case LOG_TARGET_CLASS_INVALID = 'Debug module logTarget configuration must declare a valid class name.';

    /**
     * The configured log target resolves to an incompatible instance.
     *
     * Format: "Debug module logTarget must resolve to a yii\debug\LogTarget instance."
     */
    case LOG_TARGET_INSTANCE_INVALID = 'Debug module logTarget must resolve to a yii\debug\LogTarget instance.';

    /**
     * The debug module log target has not been bootstrapped.
     *
     * Format: "Debug module logTarget has not been bootstrapped; call Module::bootstrap() first."
     */
    case LOG_TARGET_NOT_BOOTSTRAPPED = 'Debug module logTarget has not been bootstrapped; call Module::bootstrap() first.';

    /**
     * The log target is unavailable for loading debug data.
     *
     * Format: "The debug module logTarget must be initialized before loading debug data."
     */
    case LOG_TARGET_NOT_INITIALIZED_FOR_LOADING
        = 'The debug module logTarget must be initialized before loading debug data.';

    /**
     * The log target is unavailable for reading log messages.
     *
     * Format: "The debug module logTarget must be initialized before reading log messages."
     */
    case LOG_TARGET_NOT_INITIALIZED_FOR_READING
        = 'The debug module logTarget must be initialized before reading log messages.';

    /**
     * The mail collector cannot be found.
     *
     * Format: "Mail collector not found."
     */
    case MAIL_COLLECTOR_NOT_FOUND = 'Mail collector not found.';

    /**
     * A requested captured mail file cannot be found.
     *
     * Format: "Mail file not found"
     */
    case MAIL_FILE_NOT_FOUND = 'Mail file not found';

    /**
     * A panel configuration lacks a resolvable class name.
     *
     * Format: "Debug panel '%s' configuration must declare a valid class name."
     */
    case PANEL_CLASS_INVALID = "Debug panel '%s' configuration must declare a valid class name.";

    /**
     * A registration ID is declared twice.
     *
     * Format: "Duplicate debug panel ID: %s."
     */
    case PANEL_ID_DUPLICATE = 'Duplicate debug panel ID: %s.';

    /**
     * A panel configuration resolves to an object outside the panel contract.
     *
     * Format: "Debug panel '%s' must resolve to a %s instance: %s."
     */
    case PANEL_INSTANCE_INVALID = "Debug panel '%s' must resolve to a %s instance: %s.";

    /**
     * A title or icon override targets a panel that renders its own metadata.
     *
     * Format: "Debug panel '%s' registration options 'title' and 'icon' apply to portable panels only."
     */
    case PANEL_METADATA_OVERRIDE_UNSUPPORTED
        = "Debug panel '%s' registration options 'title' and 'icon' apply to portable panels only.";

    /**
     * A panel receives an unsupported hydration payload.
     *
     * Format: "a payload supported by this panel"
     */
    case PANEL_PAYLOAD_EXPECTED = 'a payload supported by this panel';

    /**
     * The debug panels have not been initialized.
     *
     * Format: "Debug panels have not been initialized."
     */
    case PANELS_NOT_INITIALIZED = 'Debug panels have not been initialized.';

    /**
     * A portable panel is rendered before any capture was hydrated into it.
     *
     * Format: "No portable panel capture has been hydrated."
     */
    case PORTABLE_PANEL_NOT_HYDRATED = 'No portable panel capture has been hydrated.';

    /**
     * The request must use the POST method.
     *
     * Format: "Only POST requests are allowed."
     */
    case POST_ONLY = 'Only POST requests are allowed.';

    /**
     * A configured registration ID does not match the provider it wraps.
     *
     * Format: "The debug %s registration ID must match its provider."
     */
    case PROVIDER_ID_MISMATCH = 'The debug %s registration ID must match its provider.';

    /**
     * A provider-backed panel is used without its declarative provider.
     *
     * Format: "A declarative debug panel provider must be configured."
     */
    case PROVIDER_PANEL_REQUIRED = 'A declarative debug panel provider must be configured.';

    /**
     * A requested queue job record cannot be found.
     *
     * Format: "Queue job record not found."
     */
    case QUEUE_JOB_RECORD_NOT_FOUND = 'Queue job record not found.';

    /**
     * Yii cannot resolve a required action service.
     *
     * Format: "Could not load required service: %s"
     */
    case REQUIRED_SERVICE_NOT_FOUND = 'Could not load required service: %s';

    /**
     * A filter banner lacks its required search model.
     *
     * Format: "%s::$searchModel must be set."
     */
    case SEARCH_MODEL_REQUIRED = '%s::$searchModel must be set.';

    /**
     * A module service definition resolves to an object outside the service contract.
     *
     * Format: "Debug module service must resolve to a %s instance."
     */
    case SERVICE_INSTANCE_INVALID = 'Debug module service must resolve to a %s instance.';

    /**
     * The debug layout lacks its required shell context.
     *
     * Format: "The debug layout requires a ShellContext."
     */
    case SHELL_CONTEXT_REQUIRED = 'The debug layout requires a ShellContext.';

    /**
     * A panel toolbar envelope breaks the typed toolbar contract.
     *
     * Format: "Debug panel '%s' returned an invalid toolbar envelope: '%s'."
     */
    case TOOLBAR_ENVELOPE_INVALID = "Debug panel '%s' returned an invalid toolbar envelope: '%s'.";

    /**
     * An application component is not a Yii web user instance.
     *
     * Format: "Application component '%s' must be a 'yii\web\User' instance."
     */
    case USER_COMPONENT_INVALID = "Application component '%s' must be a 'yii\\web\\User' instance.";

    /**
     * The user filter model does not implement the required interface.
     *
     * Format: "User filter model must implement %s."
     */
    case USER_FILTER_MODEL_INVALID = 'User filter model must implement %s.';

    /**
     * A supplied user identifier is invalid.
     *
     * Format: "Invalid user_id parameter."
     */
    case USER_ID_INVALID = 'Invalid user_id parameter.';

    /**
     * User switching cannot be configured without a debug module.
     *
     * Format: "Unable to configure user switching without a debug module."
     */
    case USER_SWITCH_MODULE_REQUIRED = 'Unable to configure user switching without a debug module.';

    /**
     * The packaged Yii logo cannot be read.
     *
     * Format: "Unable to read the packaged Yii logo."
     */
    case YII_LOGO_UNREADABLE = 'Unable to read the packaged Yii logo.';

    /**
     * Formats the message template with the supplied arguments.
     *
     * @param int|string ...$argument Values inserted into the template.
     *
     * @return string Formatted exception message.
     */
    public function getMessage(int|string ...$argument): string
    {
        return sprintf($this->value, ...$argument);
    }
}
