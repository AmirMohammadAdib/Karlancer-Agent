import json
import unittest
from pathlib import Path
from collector.parser import parse_notifications,parse_project,safe_project_url
class ParserTests(unittest.TestCase):
    def test_notification_urls_and_deduplication(self):
        data=json.loads((Path(__file__).parents[1]/'fixtures/sample.json').read_text()); notes=parse_notifications(data['notifications_html']); self.assertEqual(len(notes),1); self.assertEqual(notes[0]['project_url'],'https://www.karlancer.com/projects/demo-one')
    def test_url_safety(self):
        self.assertTrue(safe_project_url('/projects/x')); self.assertIsNone(safe_project_url('https://evil.example/projects/x')); self.assertIsNone(safe_project_url('javascript:alert(1)'))
    def test_persian_detail_and_missing_values(self):
        item=parse_project('<h1>نمونه</h1><div class="description">توضیح فارسی</div><span class="skill">PHP</span>','https://www.karlancer.com/projects/x'); self.assertEqual(item['description'],'توضیح فارسی'); self.assertEqual(item['skills'],['PHP']); self.assertIsNone(item['budget_min'])
