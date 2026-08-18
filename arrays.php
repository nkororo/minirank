<?php

/**
 * Global configuration arrays.
 *
 * Pre-computed arrays used throughout the application for validation,
 * rendering, and access-control checks.
 */

$POSITION_RANGE = range(POSITION_MIN, POSITION_MAX);

$PUBLIC_OPS = ['login', 'register'];

$AUTH_OPS = ['login', 'register', 'logout'];
