(function (root) {
    'use strict';
    const palette = ['#078f83','#315b7c','#f2b84b','#db5560','#52a7c1','#8a6fc2'];
    const numeric = value => typeof value === 'number' && Number.isFinite(value);
    const copy = value => JSON.parse(JSON.stringify(value));

    function wrapLabel(value, limit = 46) {
        const text = Array.isArray(value) ? value.join(' ') : String(value ?? '');
        const lines = [];
        let line = '';
        for (let word of text.split(/\s+/).filter(Boolean)) {
            if (line && line.length + word.length + 1 > limit) { lines.push(line); line = ''; }
            while (word.length > limit) { lines.push(word.slice(0,limit)); word = word.slice(limit); }
            line = line ? line + ' ' + word : word;
        }
        if (line) lines.push(line);
        return lines.length ? lines : [''];
    }

    function buildGraph(source, mode = 'bar', horizontal = false, composition = false, readable = false) {
        const data = copy(source);
        data.datasets.forEach(d => { d.data = d.data.map(value => typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)) ? Number(value) : value); });
        const options = {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{usePointStyle:true}}}};
        let type = mode === 'line' || mode === 'polygon' ? 'line' : mode === 'pie' ? 'pie' : 'bar';
        let note = 'Original values by category. Missing results are left blank.';
        if (mode === 'histogram' || mode === 'polygon' || (mode === 'pie' && !composition)) {
            const values = data.datasets.flatMap(d => d.data).filter(numeric);
            const min = values.length ? Math.min(...values) : 0;
            const max = values.length ? Math.max(...values) : 1;
            const count = min === max ? 1 : 5;
            const width = min === max ? Math.max(1, Math.abs(min) * .1) : (max - min) / count;
            const start = min === max ? min - width / 2 : min;
            const format = value => Number(value.toPrecision(5)).toLocaleString();
            data.labels = Array.from({length:count}, (_,i) => `${format(start+i*width)} to ${format(start+(i+1)*width)}${i === count-1 ? ' (inclusive)' : ''}`);
            data.datasets = data.datasets.map((d, series) => {
                const bins = Array(count).fill(0);
                d.data.filter(numeric).forEach(value => bins[Math.min(count-1, Math.max(0, Math.floor((value-start)/width)))]++);
                const points = bins.map((value,i) => ({x:start+(i+.5)*width,y:value}));
                if (mode === 'polygon') {
                    points.unshift({x:start-width/2,y:0});
                    points.push({x:start+(count+.5)*width,y:0});
                }
                return {label:d.label || `Series ${series+1}`, data:mode === 'polygon' ? points : bins,
                    backgroundColor:mode === 'pie' ? palette : palette[series % palette.length],
                    borderColor:mode === 'pie' ? '#fff' : palette[series % palette.length],
                    borderWidth:2, fill:false, tension:0, barPercentage:1, categoryPercentage:1};
            });
            note = 'Distribution of recorded values in equal-width ranges; missing values excluded. Ranges include their lower boundary; only the final range includes its upper boundary.';
            if (mode === 'pie') note += ' Each ring represents one series; slices show counts of results, not sums of percentages.';
            else options.scales = {x:{type:mode === 'polygon' ? 'linear' : 'category', title:{display:true,text:'Value range'}},y:{beginAtZero:true,title:{display:true,text:'Number of results'},ticks:{precision:0}}};
        } else {
            data.datasets = data.datasets.map((d,i) => ({label:d.label || 'Results',data:d.data,
                backgroundColor:mode === 'pie' ? (Array.isArray(d.backgroundColor) ? d.backgroundColor : palette) : (d.backgroundColor || palette[i % palette.length]),
                borderColor:mode === 'pie' ? '#fff' : palette[i % palette.length],borderWidth:2,fill:false,tension:0,spanGaps:false}));
            if (mode === 'pie') note = 'Share of the original category totals. Each ring represents one series.';
            else {
                options.indexAxis = mode === 'bar' && horizontal ? 'y' : 'x';
                options.scales = {x:{beginAtZero:options.indexAxis === 'y'}, y:{beginAtZero:options.indexAxis !== 'y'}};
            }
        }
        if (readable) {
            if (mode === 'bar' || mode === 'line') {
                const axis = options.indexAxis === 'y' ? 'y' : 'x';
                options.scales[axis].ticks = {autoSkip:false, maxRotation:0, minRotation:0, padding:10, font:{size:12},
                    callback:function (value) { return wrapLabel(this.getLabelForValue(value), axis === 'y' ? 46 : 22); }};
                options.plugins.tooltip = {callbacks:{title:items => items.length ? wrapLabel(data.labels[items[0].dataIndex],64) : []}};
            }
        }
        return {type,data,options,note};
    }

    function scrollableChart(document, host, controls, source) {
        const viewport = document.createElement('div');
        viewport.className = 'analysis-chart-viewport';
        viewport.tabIndex = 0;
        viewport.setAttribute('role','region');
        viewport.setAttribute('aria-label','Scrollable comparison chart. Drag horizontally or use the left and right arrow keys.');
        host.parentElement.insertBefore(viewport,host);
        viewport.appendChild(host);
        const navigation = document.createElement('div');
        navigation.className = 'analysis-chart-navigation';
        const hint = document.createElement('span');
        hint.textContent = 'Drag or scroll sideways to see the full chart';
        navigation.appendChild(hint);
        const buttons = ['left','right'].map((direction,index) => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-light'; button.textContent = index ? '→' : '←';
            button.setAttribute('aria-label','Scroll chart '+direction);
            button.title = 'Scroll chart '+direction;
            button.addEventListener('click',() => viewport.scrollBy({left:index ? 180 : -180,behavior:'smooth'}));
            navigation.appendChild(button);
            return button;
        });
        controls.appendChild(navigation);
        const update = () => {
            const maximum = viewport.scrollWidth - viewport.clientWidth;
            const overflow = maximum > 1;
            navigation.hidden = !overflow;
            viewport.classList.toggle('is-scrollable',overflow);
            buttons[0].disabled = viewport.scrollLeft <= 1;
            buttons[1].disabled = viewport.scrollLeft >= maximum - 1;
        };
        viewport.addEventListener('scroll',update,{passive:true});
        if (root.ResizeObserver) new root.ResizeObserver(update).observe(viewport);
        viewport.addEventListener('keydown',event => {
            if (viewport.scrollWidth <= viewport.clientWidth || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            if (event.key === 'Home' || event.key === 'End') viewport.scrollTo({left:event.key === 'Home' ? 0 : viewport.scrollWidth,behavior:'smooth'});
            else viewport.scrollBy({left:event.key === 'ArrowRight' ? 180 : -180,behavior:'smooth'});
        });
        let pointer = null;
        let suppressClick = false;
        viewport.addEventListener('pointerdown',event => {
            if (event.pointerType !== 'mouse' || event.button !== 0 || viewport.scrollWidth <= viewport.clientWidth) return;
            pointer = {id:event.pointerId,x:event.clientX,left:viewport.scrollLeft,moved:false};
            suppressClick = false;
        });
        viewport.addEventListener('pointermove',event => {
            if (!pointer || event.pointerId !== pointer.id) return;
            const distance = event.clientX - pointer.x;
            if (!pointer.moved && Math.abs(distance) < 5) return;
            if (!pointer.moved) {
                pointer.moved = true;
                viewport.setPointerCapture(event.pointerId);
                viewport.focus({preventScroll:true});
                viewport.classList.add('is-dragging');
            }
            event.preventDefault();
            viewport.scrollLeft = pointer.left - distance;
        });
        const release = event => {
            if (!pointer || event.pointerId !== pointer.id) return;
            suppressClick = pointer.moved;
            pointer = null;
            viewport.classList.remove('is-dragging');
            if (viewport.hasPointerCapture(event.pointerId)) viewport.releasePointerCapture(event.pointerId);
        };
        viewport.addEventListener('pointerup',release);
        viewport.addEventListener('pointercancel',release);
        viewport.addEventListener('pointerleave',() => { if (pointer && !pointer.moved) pointer = null; });
        // A pan must not accidentally toggle a chart legend or select a data point.
        viewport.addEventListener('click',event => {
            if (suppressClick) { event.preventDefault(); event.stopImmediatePropagation(); suppressClick = false; }
        },true);
        const height = parseFloat(host.style.height) || 490;
        return mode => {
            const categoryMode = mode === 'bar' || mode === 'line';
            host.style.minWidth = categoryMode ? (mode === 'bar' ? 840 : Math.max(960,source.labels.length*145))+'px' : '0';
            host.style.width = '100%';
            const lines = Math.max(1,...source.labels.map(label => wrapLabel(label).length));
            host.style.height = (mode === 'bar' ? Math.max(height,source.labels.length*(lines*15+24)+100) : height)+'px';
            viewport.scrollLeft = 0;
            root.requestAnimationFrame(update);
        };
    }

    function mount(ChartClass, document) {
        document.querySelectorAll('canvas').forEach(canvas => {
            const original = ChartClass.getChart(canvas);
            if (!original || canvas.dataset.graphSelector) return;
            canvas.dataset.graphSelector = '1';
            const source = copy(original.data);
            const horizontal = original.options.indexAxis === 'y';
            const composition = ['pie','doughnut'].includes(original.config.type);
            const controls = document.createElement('section');
            controls.className = 'analysis-graph-controls';
            const label = document.createElement('label');
            label.textContent = 'Graph type';
            const select = document.createElement('select');
            select.className = 'form-control form-control-sm';
            select.setAttribute('aria-label', `Graph type for ${canvas.id || 'analysis'}`);
            [['bar','Bar graph'],['line','Line graph'],['pie','Pie chart'],['histogram','Histogram'],['polygon','Frequency polygon']].forEach(([value,text]) => {
                const option = document.createElement('option'); option.value=value; option.textContent=text; select.appendChild(option);
            });
            label.appendChild(select); controls.appendChild(label);
            const note = document.createElement('small');
            note.className = 'text-muted'; note.setAttribute('aria-live','polite'); controls.appendChild(note);
            // Keep controls outside the dedicated responsive canvas container.
            const host = canvas.parentElement;
            host.parentElement.insertBefore(controls, host);
            const readable = canvas.dataset.chartScrollable === '1';
            const layout = readable ? scrollableChart(document,host,controls,source) : null;
            let chart = original;
            function render() {
                const graph = buildGraph(source, select.value, horizontal, composition, readable);
                chart.destroy();
                if (layout) layout(select.value || 'bar');
                chart = new ChartClass(canvas, {type:graph.type,data:graph.data,options:graph.options});
                note.textContent = graph.note;
            }
            select.addEventListener('change', render);
            render();
        });
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = {buildGraph,mount};
    if (root.document) {
        const ready = () => { if (root.Chart) mount(root.Chart, root.document); };
        if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', ready);
        else ready();
    }
})(typeof window !== 'undefined' ? window : globalThis);
