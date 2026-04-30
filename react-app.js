(function () {
  const e = React.createElement;
  const state = window.VNSTAT_APP_STATE || {};

  function q(params) {
    return new URLSearchParams(params).toString();
  }

  function encode(v) {
    return encodeURIComponent(v == null ? '' : String(v));
  }

  function formatBytesFromKb(kb) {
    let bytes = (Number(kb) || 0) * 1024;
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    let i = 0;
    while (bytes >= 1000 && i < units.length - 1) {
      bytes /= 1000;
      i += 1;
    }
    return `${bytes.toFixed(2)} ${units[i]}`;
  }

  function formatRate(bytesPerSecond) {
    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    let value = bytesPerSecond;
    let i = 0;
    while (value >= 1000 && i < units.length - 1) {
      value /= 1000;
      i += 1;
    }
    return `${value.toFixed(2)} ${units[i]}`;
  }

  function titleCase(t) {
    if (!t) return '';
    return t.charAt(0).toUpperCase() + t.slice(1);
  }

  function DataTable({ caption, rows }) {
    return e('table', { width: '100%', cellSpacing: '0' }, [
      e('caption', { key: 'c' }, caption),
      e('tr', { key: 'h' }, [
        e('th', { className: 'label', style: { width: '120px' } }, '\u00a0'),
        e('th', { className: 'label' }, 'In'),
        e('th', { className: 'label' }, 'Out'),
        e('th', { className: 'label' }, 'Total')
      ]),
      ...rows.filter((r) => r.act === 1).map((r, i) => {
        const id = i % 2 ? 'odd' : 'even';
        return e('tr', { key: `${caption}-${i}` }, [
          e('td', { className: `label_${id}` }, r.label),
          e('td', { className: `numeric_${id}` }, formatBytesFromKb(r.rx)),
          e('td', { className: `numeric_${id}` }, formatBytesFromKb(r.tx)),
          e('td', { className: `numeric_${id}` }, formatBytesFromKb((r.rx || 0) + (r.tx || 0)))
        ]);
      })
    ]);
  }

  function App() {
    const [live, setLive] = React.useState({ rx: '--', tx: '--', status: 'Actualizando cada 0.5s' });
    const lastSample = React.useRef(null);

    React.useEffect(() => {
      function update() {
        fetch(`live.php?if=${encode(state.iface)}&style=${encode(state.style)}`)
          .then((r) => r.json())
          .then((data) => {
            if (!lastSample.current) {
              lastSample.current = data;
              return;
            }
            const elapsed = (data.timestamp_ms - lastSample.current.timestamp_ms) / 1000;
            if (elapsed <= 0) {
              lastSample.current = data;
              return;
            }
            let rxRate = (data.rx_bytes - lastSample.current.rx_bytes) / elapsed;
            let txRate = (data.tx_bytes - lastSample.current.tx_bytes) / elapsed;
            if (rxRate < 0) rxRate = 0;
            if (txRate < 0) txRate = 0;
            setLive({
              rx: formatRate(rxRate),
              tx: formatRate(txRate),
              status: `Actualizado: ${new Date().toLocaleTimeString()}`
            });
            lastSample.current = data;
          })
          .catch(() => {
            setLive((prev) => ({ ...prev, status: 'Error al obtener datos en tiempo real' }));
          });
      }
      update();
      const id = setInterval(update, 1200);
      return () => clearInterval(id);
    }, []);

    const base = {
      graph: state.graph,
      style: state.style,
      show_rx: state.showRx,
      show_tx: state.showTx,
      show_total: state.showTotal
    };

    const summaryRows = [
      { act: 1, label: 'This hour', rx: state.hour?.[0]?.rx || 0, tx: state.hour?.[0]?.tx || 0 },
      { act: 1, label: 'This day', rx: state.day?.[0]?.rx || 0, tx: state.day?.[0]?.tx || 0 },
      { act: 1, label: 'This month', rx: state.month?.[0]?.rx || 0, tx: state.month?.[0]?.tx || 0 },
      {
        act: 1,
        label: 'All time',
        rx: ((state.summary?.totalrx || 0) * 1024) + (state.summary?.totalrxk || 0),
        tx: ((state.summary?.totaltx || 0) * 1024) + (state.summary?.totaltxk || 0)
      }
    ];

    const graphParams = q({
      if: state.iface,
      page: state.page,
      style: state.style,
      show_rx: state.showRx,
      show_tx: state.showTx,
      show_total: state.showTotal,
      from_date: state.fromDate,
      to_date: state.toDate
    });

    return e('div', { id: 'wrap', className: 'react-layout' }, [
      e('aside', { id: 'sidebar', key: 's' },
        e('ul', { className: 'iface' },
          (state.ifaceList || []).map((ifc) => e('li', { key: ifc, className: `iface ${state.iface === ifc ? 'active' : ''}` }, [
            e('a', { href: `${state.script}?${q({ if: ifc, ...base })}` }, titleCase(state.ifaceTitle?.[ifc] || ifc)),
            e('ul', { className: 'page' },
              (state.pageList || []).map((pg) => e('li', { key: `${ifc}-${pg}`, className: `${state.iface === ifc && state.page === pg ? 'page active' : 'page'}` },
                e('a', { href: `${state.script}?${q({ if: ifc, page: pg, ...base })}` }, titleCase(state.pageTitle?.[pg] || pg))
              ))
            )
          ]))
        )
      ),
      e('main', { id: 'content', key: 'm' }, [
        e('section', { id: 'live-traffic-panel', key: 'l' }, [
          e('div', { className: 'title' }, `Traffic data for ${state.ifaceTitle?.[state.iface] || state.iface}`),
          e('div', { className: 'metric' }, `In: ${live.rx}`),
          e('div', { className: 'metric' }, `Out: ${live.tx}`),
          e('div', { id: 'live-status' }, live.status)
        ]),
        e('div', { id: 'header', key: 'h' }, `Traffic data for ${state.ifaceTitle?.[state.iface] || ''} (${state.iface})`),
        e('div', { id: 'main', key: 'x' }, [
          state.page === 'd' ? e('form', { id: 'date-range-form', method: 'get', action: state.script, key: 'df' }, [
            e('input', { type: 'hidden', name: 'if', value: state.iface }),
            e('input', { type: 'hidden', name: 'page', value: state.page }),
            e('input', { type: 'hidden', name: 'graph', value: state.graph }),
            e('input', { type: 'hidden', name: 'style', value: state.style }),
            e('input', { type: 'hidden', name: 'show_rx', value: state.showRx }),
            e('input', { type: 'hidden', name: 'show_tx', value: state.showTx }),
            e('input', { type: 'hidden', name: 'show_total', value: state.showTotal }),
            e('div', { className: 'date-field' }, [e('label', { htmlFor: 'from_date' }, 'From Date'), e('input', { id: 'from_date', name: 'from_date', type: 'date', defaultValue: state.fromDate || '' })]),
            e('div', { className: 'date-field' }, [e('label', { htmlFor: 'to_date' }, 'To Date'), e('input', { id: 'to_date', name: 'to_date', type: 'date', defaultValue: state.toDate || '' })]),
            e('button', { type: 'submit' }, 'Buscar')
          ]) : null,

          state.page === 'd' && state.customDayError ? e('div', { className: 'date-range-message error', key: 'de' }, state.customDayError) : null,

          ['h', 'd', 'm'].includes(state.page)
            ? e('div', { style: { display: 'flex', alignItems: 'flex-start', gap: '18px' }, key: 'g' }, [
                e('div', null, state.graphFormat === 'svg'
                  ? e('object', { type: 'image/svg+xml', width: 692, height: 370, data: `graph_svg.php?${graphParams}` })
                  : e('img', { src: `graph.php?${graphParams}`, alt: 'graph' })
                ),
                e('form', { id: 'graph-series-form', method: 'get', action: state.script }, [
                  e('input', { type: 'hidden', name: 'if', value: state.iface }),
                  e('input', { type: 'hidden', name: 'page', value: state.page }),
                  e('input', { type: 'hidden', name: 'graph', value: state.graph }),
                  e('input', { type: 'hidden', name: 'style', value: state.style }),
                  state.page === 'd' ? e('input', { type: 'hidden', name: 'from_date', value: state.fromDate || '' }) : null,
                  state.page === 'd' ? e('input', { type: 'hidden', name: 'to_date', value: state.toDate || '' }) : null,
                  e('div', { className: 'date-field' }, [e('input', { type: 'hidden', name: 'show_rx', value: '0' }), e('label', null, [e('input', { type: 'checkbox', name: 'show_rx', value: '1', defaultChecked: state.showRx === '1' }), ' Entrada'])]),
                  e('div', { className: 'date-field' }, [e('input', { type: 'hidden', name: 'show_tx', value: '0' }), e('label', null, [e('input', { type: 'checkbox', name: 'show_tx', value: '1', defaultChecked: state.showTx === '1' }), ' Salida'])]),
                  e('div', { className: 'date-field' }, [e('input', { type: 'hidden', name: 'show_total', value: '0' }), e('label', null, [e('input', { type: 'checkbox', name: 'show_total', value: '1', defaultChecked: state.showTotal === '1' }), ' Total'])]),
                  e('button', { type: 'submit' }, 'Aplicar gráfico')
                ])
              ])
            : null,

          state.page === 's' ? e(React.Fragment, { key: 'ps' }, [e(DataTable, { caption: 'Summary', rows: summaryRows }), e('br'), e(DataTable, { caption: 'Top 10 days', rows: state.top || [] })]) : null,
          state.page === 'h' ? e(DataTable, { caption: 'Last 24 hours', rows: state.hour || [], key: 'ph' }) : null,
          state.page === 'd' ? e(DataTable, { caption: (state.customDayRange || []).length > 0 ? 'Días en el rango seleccionado' : 'Last 30 days', rows: (state.customDayRange || []).length > 0 ? state.customDayRange : (state.day || []), key: 'pd' }) : null,
          state.page === 'm' ? e(DataTable, { caption: 'Last 12 months', rows: state.month || [], key: 'pm' }) : null,
          state.page === 'q' ? e('div', { className: 'query-layout', key: 'pq' }, [
            e('div', { className: 'query-controls' }, [
              e('form', { id: 'query-form', method: 'get', action: state.script }, [
                e('input', { type: 'hidden', name: 'if', value: state.iface }),
                e('input', { type: 'hidden', name: 'page', value: 'q' }),
                e('input', { type: 'hidden', name: 'graph', value: 'none' }),
                e('input', { type: 'hidden', name: 'style', value: state.style }),
                e('div', { className: 'date-field' }, [e('label', { htmlFor: 'q_from_date' }, 'From Date'), e('input', { id: 'q_from_date', name: 'q_from_date', type: 'date', defaultValue: state.queryFromDate || '' })]),
                e('div', { className: 'date-field' }, [e('label', { htmlFor: 'q_to_date' }, 'To Date'), e('input', { id: 'q_to_date', name: 'q_to_date', type: 'date', defaultValue: state.queryToDate || '' })]),
                e('div', { className: 'date-field' }, [e('label', { htmlFor: 'q_group' }, 'Agrupar por'), e('select', { id: 'q_group', name: 'q_group', defaultValue: state.queryGroup }, [
                  e('option', { value: 'y' }, 'Años'), e('option', { value: 'm' }, 'Meses'), e('option', { value: 'd' }, 'Días'), e('option', { value: 'h' }, 'Horas')
                ])]),
                e('button', { type: 'submit' }, 'Go')
              ]),
              e('div', { className: 'query-meta' }, `Search found ${state.queryTotalRows || 0} results.`),
              e('a', { className: 'query-export', href: `${state.script}?${q({ if: state.iface, page: 'q', graph: 'none', style: state.style, q_group: state.queryGroup, q_from_date: state.queryFromDate, q_to_date: state.queryToDate, q_sort: state.querySort, q_dir: state.queryDir, export: 1 })}` }, 'Export Results')
            ]),
            e('div', { className: 'query-results' }, [
              e('table', { width: '100%', cellSpacing: '0' }, [
                e('caption', null, 'Consultas'),
                e('tr', null, ['date', 'rx', 'tx', 'total'].map((sortKey) => {
                  const labels = { date: 'Date', rx: 'Download', tx: 'Upload', total: 'Combined' };
                  const nextDir = state.querySort === sortKey && state.queryDir === 'asc' ? 'desc' : 'asc';
                  const indicator = state.querySort === sortKey ? (state.queryDir === 'asc' ? ' ▲' : ' ▼') : '';
                  return e('th', { className: 'label', key: sortKey },
                    e('a', {
                      className: 'query-sort-link',
                      href: `${state.script}?${q({ if: state.iface, page: 'q', graph: 'none', style: state.style, q_group: state.queryGroup, q_from_date: state.queryFromDate, q_to_date: state.queryToDate, q_sort: sortKey, q_dir: nextDir, q_page: 1 })}`
                    }, `${labels[sortKey]}${indicator}`)
                  );
                })),
                ...(state.queryRows || []).map((r, i) => {
                  const id = i % 2 ? 'odd' : 'even';
                  return e('tr', { key: `qr-${i}` }, [
                    e('td', { className: `label_${id}` }, r.label),
                    e('td', { className: `numeric_${id}` }, formatBytesFromKb(r.rx)),
                    e('td', { className: `numeric_${id}` }, formatBytesFromKb(r.tx)),
                    e('td', { className: `numeric_${id}` }, formatBytesFromKb((r.rx || 0) + (r.tx || 0)))
                  ]);
                })
              ]),
              e('div', { className: 'query-pagination' }, [
                e('span', null, `Displaying ${(state.queryTotalRows || 0) > 0 ? (state.queryStart + 1) : 0} to ${(state.queryStart + (state.queryRows || []).length)} of ${state.queryTotalRows || 0} items`),
                state.queryPage > 1 ? e('a', { href: `${state.script}?${q({ if: state.iface, page: 'q', graph: 'none', style: state.style, q_group: state.queryGroup, q_from_date: state.queryFromDate, q_to_date: state.queryToDate, q_sort: state.querySort, q_dir: state.queryDir, q_page: state.queryPage - 1 })}` }, '« Anterior') : null,
                state.queryPage < state.queryTotalPages ? e('a', { href: `${state.script}?${q({ if: state.iface, page: 'q', graph: 'none', style: state.style, q_group: state.queryGroup, q_from_date: state.queryFromDate, q_to_date: state.queryToDate, q_sort: state.querySort, q_dir: state.queryDir, q_page: state.queryPage + 1 })}` }, 'Siguiente »') : null
              ])
            ])
          ]) : null
        ]),
        e('div', { id: 'footer', key: 'f' }, [
          e('a', { href: 'http://www.sqweek.com/' }, 'vnStat PHP frontend'),
          ' 2.0.0 - ©2006-2011 Bjorge Dijkstra (bjd _at_ jooz.net)'
        ])
      ]) 
    ]);
  }

  ReactDOM.createRoot(document.getElementById('app')).render(e(App));
})();
