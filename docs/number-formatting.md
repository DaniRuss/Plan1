# Displaying numbers

Use `App\Support\NumberFormatter::format($value)` for visible numeric values in HTML, PDF, Word and CSV output. Values are rounded half up to at most three decimals, with trailing zeros removed. Null remains blank; placeholders such as N/A and - remain unchanged. Objective codes, identifiers, dates and times are not quantities.

Numeric input attributes must use the ungrouped form `format($value, 3, '.', '')` and a `data-original-number` containing the unrounded source. The shared JavaScript restores unchanged precision on submission. Decimal inputs permit fractional values with step=any; business constraints remain enforced on the server.

All three application layouts load the shared versioned number-format.js. Its numeric jQuery value hook preserves full precision for calculations and programmatic updates while displaying rounded values. Native numeric controls are formatted on blur. Use `setNumberInput(element, rawValue)` and `numberInputValue(element)` for numeric controls outside jQuery. Never pass a rounded display value into a stored calculation.

Spreadsheet numeric cells keep numeric source values and use optional three-decimal number formats. Percentage strings in project and employee exports use the same formatter.

Checks: `node tests/js/number-format.test.cjs` and `php artisan test --filter=NumberFormatterTest`. SharedReportingTest covers rendered values and unchanged database precision.
