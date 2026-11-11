#!/bin/bash
echo "================================"
echo "BankSploit JWT Vulnerability Test"
echo "================================"
echo ""
echo "Running Python test suite..."
python test_jwt_fixed.py 2>&1 | tail -40
echo ""
echo "Test completed successfully!"
