// A check of the formula core of the comparison table, run with node from the project folder: node tests/js/comparison-core.test.js
const core = require('../../public/assets/ui/comparison-core.js');
let failed = 0;
const check = (what, ok, detail) => { console.log((ok ? 'ok    ' : 'FAIL  ') + what + (ok || !detail ? '' : '  ' + detail)); failed += ok ? 0 : 1; };

// A stand-in for math.js that only adds, subtracts, multiplies and divides numbers in parentheses
const math = { evaluate: (e) => { if (!/^[-+*/().\d\s]+$/.test(e)) throw new Error('bad'); return Function('return ' + e)(); } };
const cells = { '10|price': '4', '10|qty': '3', '11|price': '5', '12|price': 'abc' };
const read = (ref) => cells[(ref.entryId) + '|' + ref.fieldId] ?? null;

check('token with only a field', JSON.stringify(core.parseToken('price', '10')) === JSON.stringify({ modelId: null, entryId: '10', dataId: null, fieldId: 'price' }));
check('token with an entry', core.parseToken('e12:price', '10').entryId === '12');
check('token with a model', core.parseToken('m3:price', '10').modelId === '3');
check('token with a column id', core.parseToken('d5:price', '10').dataId === 'd5');
check('token with model and entry', (() => { const r = core.parseToken('m3:E12:price', '10'); return r.modelId === '3' && r.entryId === '12' && r.fieldId === 'price'; })());

check('inject puts the numbers in brackets', core.inject('[price] * [qty]', '10', read) === '(4) * (3)');
check('a missing cell counts as 0', core.inject('[nothing]', '10', read) === '(0)');
check('a cell that is not a number counts as 0', core.inject('[e12:price]', '10', read) === '(0)');
check('evaluate multiplies', core.evaluate(math, '[price] * [qty]', '10', read) === 12);
check('evaluate reads another entry', core.evaluate(math, '[e11:price] - [price]', '10', read) === 1);
check('evaluate hides float noise', core.evaluate({ evaluate: () => 0.1 + 0.2 }, '1', '10', read) === 0.3);
check('a result that is not a number gives null', core.evaluate({ evaluate: () => ({}) }, '1', '10', read) === null);
check('a result that is not finite gives null', core.evaluate(math, '1 / 0', '10', read) === null);
check('a syntax error gives null', core.evaluate(math, '1 +* 2', '10', read) === null);
check('a blocked word gives null', core.evaluate({ evaluate: () => 5 }, 'import(1)', '10', read) === null);
check('an assignment gives null', core.evaluate({ evaluate: () => 5 }, 'x = 5', '10', read) === null);
check('a string gives null', core.evaluate({ evaluate: () => 5 }, '"a"', '10', read) === null);

check('move forward', core.move([1, 2, 3, 4], 0, 2).join() === '2,3,1,4');
check('move backward', core.move([1, 2, 3, 4], 3, 1).join() === '1,4,2,3');
check('move out of range changes nothing', core.move([1, 2], 0, 5).join() === '1,2');
check('move does not change the original', (() => { const a = [1, 2, 3]; core.move(a, 0, 2); return a.join() === '1,2,3'; })());

check('flatten nested data like jQuery', JSON.stringify(core.flatten({ id: 'x', model: { name: 'M', fields: [{ id: 'a' }] } }, '', [])) === JSON.stringify([['id', 'x'], ['model[name]', 'M'], ['model[fields][0][id]', 'a']]));
check('flatten turns null into an empty text', JSON.stringify(core.flatten({ a: null }, '', [])) === JSON.stringify([['a', '']]));

console.log(failed === 0 ? 'all ok' : failed + ' failed');
process.exit(failed === 0 ? 0 : 1);
