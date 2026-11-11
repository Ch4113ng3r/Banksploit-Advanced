#!/usr/bin/env python3
"""
Convert src/index.html to PDF using weasyprint
"""

import sys
import os

try:
    from weasyprint import HTML, CSS
except ImportError:
    print("[*] Installing weasyprint...")
    os.system(f"{sys.executable} -m pip install weasyprint -q")
    from weasyprint import HTML, CSS

def convert():
    html_file = os.path.join(os.path.dirname(__file__), 'src', 'index.html')
    pdf_file = os.path.join(os.path.dirname(__file__), 'BankSploit_CTF_Guide.pdf')

    if not os.path.exists(html_file):
        print(f"[X] File not found: {html_file}")
        sys.exit(1)

    print(f"[*] Converting: {html_file}")
    print(f"[*] Output: {pdf_file}")
    print("[*] This may take a minute...")

    try:
        # Convert HTML to PDF
        HTML(filename=html_file).write_pdf(
            pdf_file,
            optimize_size=['shapes', 'images'],
            zoom=1.0,
            dpi=300
        )

        file_size = os.path.getsize(pdf_file) / (1024 * 1024)
        print(f"\n[✓] PDF created successfully!")
        print(f"[✓] Location: {pdf_file}")
        print(f"[✓] File size: {file_size:.2f} MB")
        return 0

    except Exception as e:
        print(f"[X] Conversion failed: {str(e)}")
        return 1

if __name__ == '__main__':
    sys.exit(convert())
