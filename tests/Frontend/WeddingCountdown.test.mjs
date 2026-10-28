import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';

const source = await readFile(new URL('../../resources/js/lib/wedding-countdown.ts', import.meta.url), 'utf8');
const javascript = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext } }).outputText;
const { weddingInstant, countdownUnits } = await import(`data:text/javascript;base64,${Buffer.from(javascript).toString('base64')}`);

test('canonical Manila date/time resolves to an explicit instant including midnight across UTC dates', () => {
    assert.equal(weddingInstant('2027-02-20', '15:00', 'Asia/Manila'), Date.parse('2027-02-20T07:00:00Z'));
    assert.equal(weddingInstant('2027-02-20', '00:00', 'Asia/Manila'), Date.parse('2027-02-19T16:00:00Z'));
    assert.equal(weddingInstant('2028-02-29', '23:59', 'UTC'), Date.parse('2028-02-29T23:59:00Z'));
});
test('named zones resolve daylight saving and fractional offsets without relying on browser local time', () => {
    assert.equal(weddingInstant('2027-07-01', '12:00', 'America/New_York'), Date.parse('2027-07-01T16:00:00Z'));
    assert.equal(weddingInstant('2027-01-01', '12:00', 'America/New_York'), Date.parse('2027-01-01T17:00:00Z'));
    assert.equal(weddingInstant('2027-02-20', '09:15', 'Asia/Kathmandu'), Date.parse('2027-02-20T03:30:00Z'));
});
test('invalid, missing, ambiguous and nonexistent wall times produce no fabricated countdown target', () => {
    for (const [date, time, zone] of [
        [null, '15:00', 'Asia/Manila'],
        ['2027-02-20', null, 'Asia/Manila'],
        ['2027-02-30', '15:00', 'Asia/Manila'],
        ['2027-02-20', '24:00', 'Asia/Manila'],
        ['02/20/2027', '15:00', 'Asia/Manila'],
        ['2027-02-20', '15:00', 'Not/A_Zone'],
        ['2027-02-20', '15:00', '+08:00'],
        ['2027-11-07', '01:30', 'America/New_York'],
        ['2027-03-14', '02:30', 'America/New_York'],
    ])
        assert.equal(weddingInstant(date, time, zone), null, `${date} ${time} ${zone}`);
});
test('future units, the exact start and past dates never show negative or premature zero values', () => {
    const target = Date.parse('2027-02-20T07:00:00Z');
    assert.deepEqual(countdownUnits(target, target - 90061000), [1, 1, 1, 1]);
    assert.deepEqual(countdownUnits(target, target - 500), [0, 0, 0, 1]);
    assert.deepEqual(countdownUnits(target, target), [0, 0, 0, 0]);
    assert.deepEqual(countdownUnits(target, target + 1000), [0, 0, 0, 0]);
});
