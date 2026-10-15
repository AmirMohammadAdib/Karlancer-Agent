import os
from pathlib import Path
BASE_URL = os.getenv('LARAVEL_BASE_URL','http://127.0.0.1:8000').rstrip('/')
TOKEN = os.getenv('COLLECTOR_TOKEN','').strip()
PROFILE_DIR = Path(os.getenv('KARLANCER_PROFILE_DIR', str(Path.home()/'.karlancer-playwright')))
NAV_TIMEOUT_MS = int(os.getenv('PLAYWRIGHT_NAV_TIMEOUT_MS','45000'))
READY_TIMEOUT_MS = int(os.getenv('PLAYWRIGHT_READY_TIMEOUT_MS','15000'))
NAV_RETRIES = max(1, int(os.getenv('PLAYWRIGHT_NAV_RETRIES','2')))
