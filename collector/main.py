import argparse,json
from datetime import datetime,timezone
from pathlib import Path
from .config import BASE_URL,TOKEN,PROFILE_DIR
from .parser import parse_notifications,parse_project
from .client import CollectorClient
def payload(items): return {'schema_version':'1.0','source':'karlancer','collector':'playwright','collected_at':datetime.now(timezone.utc).isoformat(),'items':items}
def fixture_mode(path):
    data=json.loads(Path(path).read_text(encoding='utf-8')); notifications=parse_notifications(data['notifications_html']); items=[]
    for n in notifications:
        item=parse_project(data['projects_html'][n['project_url']],n['project_url']); item['notification_id']=n.get('notification_id'); items.append(item)
    return payload(items)
def interactive():
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        browser=p.chromium.launch(headless=False); context=browser.new_context(storage_state=None); page=context.new_page(); page.goto('https://www.karlancer.com/panel/notifications',wait_until='domcontentloaded',timeout=30000); input('در مرورگر وارد شوید و پس از نمایش اعلان‌ها Enter بزنید...'); notes=parse_notifications(page.content()); items=[]
        for n in notes:
            detail=context.new_page(); detail.goto(n['project_url'],wait_until='domcontentloaded',timeout=30000); items.append(parse_project(detail.content(),n['project_url'])); detail.close()
        result=payload(items); Path('collector-preview.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8'); browser.close(); return result
if __name__=='__main__':
    ap=argparse.ArgumentParser(); ap.add_argument('--fixture'); ap.add_argument('--send',action='store_true'); args=ap.parse_args(); result=fixture_mode(args.fixture) if args.fixture else interactive(); print(json.dumps(result,ensure_ascii=False,indent=2));
    if args.send:
        if not TOKEN: raise SystemExit('COLLECTOR_TOKEN تنظیم نشده است')
        status,body=CollectorClient(BASE_URL,TOKEN).send(result); print('Laravel response:',status,body)
