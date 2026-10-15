import re
from urllib.parse import urljoin, urlparse
from bs4 import BeautifulSoup
ALLOWED_HOST='www.karlancer.com'; PROJECT_PREFIX='/projects/'
UI_NOISE={'مشاهده جزئیات','پیشنهادهای این پروژه','گزارش تخلف','ارسال پیشنهاد','ذخیره پروژه','اشتراک‌گذاری'}
PERSIAN_DIGITS=str.maketrans('۰۱۲۳۴۵۶۷۸۹٬٫','0123456789,.')
def clean(value): return re.sub(r'\s+',' ',str(value or '')).strip()
def safe_project_url(value,base='https://www.karlancer.com'):
    url=urljoin(base,value or ''); p=urlparse(url)
    return url.split('#',1)[0] if p.scheme=='https' and p.netloc==ALLOWED_HOST and p.path.startswith(PROJECT_PREFIX) else None
def parse_notifications(html):
    soup=BeautifulSoup(html,'html.parser'); out=[]; seen=set()
    for a in soup.select('a[href]'):
        url=safe_project_url(a.get('href')); text=clean(a.get_text(' ',strip=True))
        if url and text and url not in seen: seen.add(url); out.append({'notification_id':None,'title':text,'project_url':url,'type':'project'})
    return out
def _numbers(text):
    value=clean(text).translate(PERSIAN_DIGITS).replace(',',''); return [float(x) for x in re.findall(r'(?<![\w])\d+(?:\.\d+)?',value)]
def _first_text(soup,selectors):
    for selector in selectors:
        node=soup.select_one(selector)
        if node and clean(node.get_text(' ',strip=True)): return clean(node.get_text(' ',strip=True))
    return None
def _skills(soup):
    nodes=soup.select('[data-skill], [data-testid*=skill], .project-skills li, .skills li, [class~=skill]'); values=[]
    for node in nodes:
        for part in re.split(r'[,،|•·\n]+',clean(node.get_text(' ',strip=True))):
            part=clean(part)
            if part and part not in UI_NOISE and len(part)<80 and part not in values: values.append(part)
    return values
def parse_project(html,url):
    soup=BeautifulSoup(html,'html.parser'); title=_first_text(soup,['h1','[data-testid=project-title]'])
    if not title and soup.title: title=clean(soup.title.get_text())
    description=_first_text(soup,['[data-description]','[data-testid=project-description]','#project-description','.project-description','.description'])
    budget_text=_first_text(soup,['[data-budget]','[data-testid*=budget]','.project-budget','.budget','.price']); nums=_numbers(budget_text or '')
    currency=None
    for token in re.findall(r'[\u0600-\u06ffA-Za-z]{2,12}',budget_text or ''):
        if token.lower() not in {'از','تا','بودجه','قیمت','budget','price'}: currency=token; break
    time_node=soup.select_one('time[datetime]'); date=time_node.get('datetime') if time_node and time_node.get('datetime') else _first_text(soup,['[data-published-at]','[data-testid*=date]','.published-at','.project-date'])
    if not date:
        match=re.search(r'(\d{4}[/-]\d{1,2}[/-]\d{1,2}|[۰-۹]{4}[/-][۰-۹]{1,2}[/-][۰-۹]{1,2}|\d+\s+(?:روز|ساعت|ماه) پیش)',clean(soup.get_text(' ',strip=True))); date=match.group(1) if match else None
    item={'notification_id':None,'type':'project','title':title,'description':description,'project_url':safe_project_url(url),'external_project_id':None,'budget_min':nums[0] if nums else None,'budget_max':nums[1] if len(nums)>1 else (nums[0] if nums else None),'currency':currency,'skills':_skills(soup),'category':_first_text(soup,['[data-category]','[data-testid*=category]','.project-category','.category']),'published_at':date,'project_status':_first_text(soup,['[data-status]','[data-testid*=status]','.project-status','.status'])}
    item['extraction_diagnostics']={'description':'ok' if description else 'missing','budget':'ok' if nums else 'missing','skills':'ok' if item['skills'] else 'missing','published_at':'ok' if date else 'missing'}
    return item
