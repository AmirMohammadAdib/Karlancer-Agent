from urllib.parse import urljoin, urlparse
from bs4 import BeautifulSoup
ALLOWED_HOST='www.karlancer.com'; PROJECT_PREFIX='/projects/'
def safe_project_url(value, base='https://www.karlancer.com'):
    url=urljoin(base, value or ''); p=urlparse(url)
    if p.scheme!='https' or p.netloc!=ALLOWED_HOST or not p.path.startswith(PROJECT_PREFIX): return None
    return url.split('#',1)[0]
def parse_notifications(html):
    soup=BeautifulSoup(html,'html.parser'); out=[]; seen=set()
    for a in soup.select('a[href]'):
        url=safe_project_url(a.get('href')); text=' '.join(a.get_text(' ',strip=True).split())
        if url and text and url not in seen: seen.add(url); out.append({'notification_id':None,'title':text,'project_url':url,'type':'project'})
    return out
def parse_project(html,url):
    soup=BeautifulSoup(html,'html.parser'); title=(soup.find('h1') or soup.find('title'))
    title=title.get_text(' ',strip=True) if title else None
    description=None
    for node in soup.select('[data-description], .description, [class*=description]'):
        value=node.get_text(' ',strip=True)
        if value: description=value; break
    skills=[x.get_text(' ',strip=True) for x in soup.select('[data-skill], .skill, [class*=skill]') if x.get_text(' ',strip=True)]
    return {'notification_id':None,'type':'project','title':title,'description':description,'project_url':safe_project_url(url),'external_project_id':None,'budget_min':None,'budget_max':None,'currency':None,'skills':list(dict.fromkeys(skills)),'category':None,'published_at':None,'project_status':None}
