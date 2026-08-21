<?php

declare(strict_types=1);

namespace yii\debug\exception;

use function sprintf;

/**
 * Defines the exception message templates authored by the Yii debug adapter.
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
     * A duplicate query lacks its occurrence count.
     *
     * Format: "Missing duplicate count for query: %s"
     */
    case DUPLICATE_QUERY_COUNT_MISSING = 'Missing duplicate count for query: %s';

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
     * A panel receives an unsupported hydration payload.
     *
     * Format: "a payload supported by this panel"
     */
    case PANEL_PAYLOAD_EXPECTED = 'a payload supported by this panel';

    /**
     * The request must use the POST method.
     *
     * Format: "Only POST requests are allowed."
     */
    case POST_ONLY = 'Only POST requests are allowed.';

    /**
     * The profiling panel cannot be resolved.
     *
     * Format: "Unable to determine the profiling panel"
     */
    case PROFILING_PANEL_UNAVAILABLE = 'Unable to determine the profiling panel';

    /**
     * A requested queue job record cannot be found.
     *
     * Format: "Queue job record not found."
     */
    case QUEUE_JOB_RECORD_NOT_FOUND = 'Queue job record not found.';

    /**
     * The request end time cannot be determined.
     *
     * Format: "Unable to determine request end time"
     */
    case REQUEST_END_TIME_UNAVAILABLE = 'Unable to determine request end time';

    /**
     * The request memory usage cannot be determined.
     *
     * Format: "Unable to determine used memory in request"
     */
    case REQUEST_MEMORY_UNAVAILABLE = 'Unable to determine used memory in request';

    /**
     * The request start time cannot be determined.
     *
     * Format: "Unable to determine request start time"
     */
    case REQUEST_START_TIME_UNAVAILABLE = 'Unable to determine request start time';

    /**
     * A filter banner lacks its required search model.
     *
     * Format: "%s::$searchModel must be set."
     */
    case SEARCH_MODEL_REQUIRED = '%s::$searchModel must be set.';

    /**
     * The debug layout lacks its required shell context.
     *
     * Format: "The debug layout requires a ShellContext."
     */
    case SHELL_CONTEXT_REQUIRED = 'The debug layout requires a ShellContext.';

    /**
     * A timeline duration is zero.
     *
     * Format: "Duration cannot be zero"
     */
    case TIMELINE_DURATION_ZERO = 'Duration cannot be zero';

    /**
     * A timeline SVG class does not extend the required base class.
     *
     * Format: "Timeline SVG class must extend %s."
     */
    case TIMELINE_SVG_CLASS_INVALID = 'Timeline SVG class must extend %s.';

    /**
     * A timeline SVG factory creates an incompatible instance.
     *
     * Format: "Timeline SVG factory must create %s."
     */
    case TIMELINE_SVG_FACTORY_INVALID = 'Timeline SVG factory must create %s.';

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
