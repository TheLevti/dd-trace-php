--TEST--
Sidecar should be disabled when telemetry is disabled
--SKIPIF--
<?php include 'startup_logging_skipif_unix.inc'; ?>
--FILE--
<?php
include_once 'startup_logging.inc';

// On Unix, we disable the sidecar in all cases if telemetry is disabled.
// So in this test, the sidecar should be disabled all the time
$logs = dd_get_startup_logs([], [
    'DD_TRACE_DEBUG' => '1',
    'DD_INSTRUMENTATION_TELEMETRY_ENABLED' => '0',
]);

dd_dump_startup_logs($logs, [
    'instrumentation_telemetry_enabled',
    'sidecar_trace_sender',
]);

?>
--EXPECT--
instrumentation_telemetry_enabled: false
sidecar_trace_sender: false
