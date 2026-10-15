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
        context = p.chromium.launch_persistent_context(
    str(PROFILE_DIR),
    channel="chrome",
    headless=False
); page=context.new_page(); page.goto('https://www.karlancer.com/panel/notifications',wait_until='domcontentloaded',timeout=30000); input('در مرورگر وارد شوید و پس از نمایش اعلان‌ها Enter بزنید...'); notes=parse_notifications(page.content()); items=[]
        for n in notes:
            detail=context.new_page()
            try:
                detail.goto(n['project_url'],wait_until='domcontentloaded',timeout=30000); item=parse_project(detail.content(),n['project_url']); item['notification_id']=n.get('notification_id'); items.append(item)
            except Exception as exc:
                items.append({'notification_id':n.get('notification_id'),'type':'project','title':n.get('title'),'description':None,'project_url':n['project_url'],'external_project_id':None,'budget_min':None,'budget_max':None,'currency':None,'skills':[],'category':None,'published_at':None,'project_status':None,'extraction_diagnostics':{'status':'failed','error':type(exc).__name__}})
            finally: detail.close()
        result=payload(items); Path('collector-preview.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8'); context.close(); return result
def inspect_page(url):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        context=p.chromium.launch_persistent_context(str(PROFILE_DIR),channel='chrome',headless=False); page=context.new_page(); page.goto(url,wait_until='domcontentloaded',timeout=30000); input('پس از رندر صفحه Enter بزنید...'); soup=__import__('bs4').BeautifulSoup(page.content(),'html.parser')
        for node in soup.select('script,style,noscript,input,textarea'): node.decompose()
        Path('collector-dom-sanitized.html').write_text(soup.prettify()[:200000],encoding='utf-8'); print('DOM sanitized در collector-dom-sanitized.html ذخیره شد.'); context.close()
if __name__=='__main__':
    ap=argparse.ArgumentParser(); ap.add_argument('--fixture'); ap.add_argument('--inspect-url'); ap.add_argument('--send',action='store_true'); args=ap.parse_args();
    if args.inspect_url: inspect_page(args.inspect_url); raise SystemExit(0)
    result=fixture_mode(args.fixture) if args.fixture else interactive(); print(json.dumps(result,ensure_ascii=False,indent=2));
    if args.send:
        if not TOKEN: raise SystemExit('COLLECTOR_TOKEN تنظیم نشده است')
        status,body=CollectorClient(BASE_URL,TOKEN).send(result); print('Laravel response:',status,body)
