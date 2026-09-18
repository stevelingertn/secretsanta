<?php

namespace App\Services\Import;

use RuntimeException;

/** Thrown to force a transaction rollback for --dry-run while still returning the summary. */
class ImportDryRunAborted extends RuntimeException {}
