import argparse,json
import time
from datetime import datetime,timezone
from pathlib import Path
from .config import BASE_URL,TOKEN,PROFILE_DIR,NAV_TIMEOUT_MS,READY_TIMEOUT_MS,NAV_RETRIES
from .parser import parse_notifications,parse_project
from .client import CollectorClient
def payload(items): return {'schema_version':'1.0','source':'karlancer','collector':'playwright','collected_at':datetime.now(timezone.utc).isoformat(),'items':items}
def navigate_with_retries(page,url,ready=None,timeout_ms=NAV_TIMEOUT_MS,retries=NAV_RETRIES):
    """Navigate with bounded commit attempts; never treats a timeout as readiness."""
    for attempt in range(1,retries+1):
        try:
            page.goto(url,wait_until='commit',timeout=timeout_ms)
        except Exception as exc:
            if exc.__class__.__name__ != 'TimeoutError': raise
            print(f'تنقل کند بود (تلاش {attempt}/{retries}). صفحه فعلی بررسی می‌شود؛ دورزدن محدودیت انجام نمی‌شود.')
        try:
            if ready is None or ready(page, READY_TIMEOUT_MS): return True
        except Exception:
            pass
        if attempt < retries:
            print('صفحه هنوز آماده نیست؛ تلاش محدود بعدی انجام می‌شود.')
    return False
def _body_ready(page,timeout_ms):
    page.wait_for_selector('body',timeout=timeout_ms)
    return bool(page.locator('body').inner_text(timeout=timeout_ms).strip())
def classify_notification_page(url,html):
    """Classify only sanitized structural signals; never logs page HTML."""
    from urllib.parse import urlparse
    from bs4 import BeautifulSoup
    path=urlparse(url).path
    soup=BeautifulSoup(html,'html.parser'); candidates=parse_notifications(html)
    body=soup.body
    text=' '.join((body.get_text(' ',strip=True) if body else '').split())
    login=path.rstrip('/').endswith('/login') or bool(soup.select('input[type=password]')) or 'ورود' in text[:300]
    access_error=any(marker in text.lower() for marker in ('captcha','access denied','forbidden','403','دسترسی غیرمجاز'))
    if login: kind='login'
    elif access_error: kind='unexpected'
    elif path.rstrip('/').endswith('/notifications') and candidates: kind='populated'
    elif path.rstrip('/').endswith('/notifications') and body and text: kind='empty'
    elif not body or not text: kind='still_loading'
    else: kind='unexpected'
    return {'classification':kind,'path':path or '/','candidate_count':len(candidates)}
def notification_state(page):
    return classify_notification_page(page.url,page.content())
def wait_for_notification_state(page,timeout_ms=READY_TIMEOUT_MS):
    deadline=time.monotonic()+timeout_ms/1000
    last=None
    while time.monotonic()<deadline:
        last=notification_state(page)
        if last['classification'] in {'login','populated','empty','unexpected'}: return last
        page.wait_for_timeout(min(250,max(1,int((deadline-time.monotonic())*1000))))
    return last or {'classification':'still_loading','path':'/','candidate_count':0}
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
); page=context.new_page()
        if not navigate_with_retries(page,'https://www.karlancer.com/panel/notifications',_body_ready):
            raise RuntimeError('صفحه اعلان‌ها بارگذاری نشد؛ مرورگر را بررسی و دوباره اجرا کنید.')
        input('در مرورگر وارد شوید و پس از نمایش اعلان‌ها Enter بزنید...')
        state=wait_for_notification_state(page)
        if state['classification']=='login': raise RuntimeError(f"هنوز صفحه ورود باز است (path={state['path']})؛ ورود دستی را کامل کنید و دوباره اجرا کنید.")
        if state['classification']=='still_loading': raise RuntimeError(f"صفحه اعلان‌ها آماده نشد (path={state['path']}, candidates={state['candidate_count']})؛ صفحه را دستی Refresh کنید و دوباره اجرا کنید.")
        if state['classification']=='unexpected': raise RuntimeError(f"صفحه غیرمنتظره است (path={state['path']}, candidates={state['candidate_count']})؛ به اعلان‌ها برگردید و دوباره اجرا کنید.")
        notes=parse_notifications(page.content())
        if state['classification']=='empty' or not notes: raise RuntimeError(f"صفحه اعلان‌ها آماده است اما پروژه‌ای ندارد (path={state['path']}, candidates={state['candidate_count']})؛ import انجام نمی‌شود.")
        items=[]
        for n in notes:
            detail=context.new_page()
            try:
                if not navigate_with_retries(detail,n['project_url'],_body_ready): raise RuntimeError('جزئیات پروژه آماده نشد')
                item=parse_project(detail.content(),n['project_url']); item['notification_id']=n.get('notification_id'); items.append(item)
            except Exception as exc:
                items.append({'notification_id':n.get('notification_id'),'type':'project','title':n.get('title'),'description':None,'project_url':n['project_url'],'external_project_id':None,'budget_min':None,'budget_max':None,'currency':None,'skills':[],'category':None,'published_at':None,'project_status':None,'extraction_diagnostics':{'status':'failed','error':type(exc).__name__}})
            finally: detail.close()
        result=payload(items); Path('collector-preview.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8'); context.close(); return result
def inspect_page(url):
    from playwright.sync_api import sync_playwright
    with sync_playwright() as p:
        context=p.chromium.launch_persistent_context(str(PROFILE_DIR),channel='chrome',headless=False); page=context.new_page()
        if not navigate_with_retries(page,url,_body_ready): raise RuntimeError('صفحه برای inspection آماده نشد؛ دوباره تلاش کنید.')
        input('پس از رندر صفحه Enter بزنید...'); soup=__import__('bs4').BeautifulSoup(page.content(),'html.parser')
        for node in soup.select('script,style,noscript,input,textarea'): node.decompose()
        Path('collector-dom-sanitized.html').write_text(soup.prettify()[:200000],encoding='utf-8'); print('DOM sanitized در collector-dom-sanitized.html ذخیره شد.'); context.close()
if __name__=='__main__':
    ap=argparse.ArgumentParser(); ap.add_argument('--fixture'); ap.add_argument('--inspect-url'); ap.add_argument('--send',action='store_true'); args=ap.parse_args();
    if args.inspect_url: inspect_page(args.inspect_url); raise SystemExit(0)
    result=fixture_mode(args.fixture) if args.fixture else interactive(); print(json.dumps(result,ensure_ascii=False,indent=2));
    if args.send:
        if not TOKEN: raise SystemExit('COLLECTOR_TOKEN تنظیم نشده است')
        status,body=CollectorClient(BASE_URL,TOKEN).send(result); print('Laravel response:',status,body)
