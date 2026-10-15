import os
from pathlib import Path
BASE_URL = os.getenv('LARAVEL_BASE_URL','http://127.0.0.1:8000').rstrip('/')
TOKEN = os.getenv('COLLECTOR_TOKEN','').strip()
PROFILE_DIR = Path(os.getenv('KARLANCER_PROFILE_DIR', str(Path.home()/'.karlancer-playwright')))
