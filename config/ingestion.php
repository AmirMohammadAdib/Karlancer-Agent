<?php
return ['mock_enabled'=>(bool) env('MOCK_IMPORTS_ENABLED', false), 'cron'=>(string) env('MOCK_IMPORT_CRON','0 * * * *')];
