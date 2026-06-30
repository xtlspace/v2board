<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no">
    <title>{{$title}} - 订阅日志</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f0f2f5; color: #333; }
        a { text-decoration: none; color: inherit; }

        .header { height: 64px; background: #fff; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 50; }
        .header h1 { font-size: 18px; font-weight: 600; color: #333; }

        .content { padding: 24px; max-width: 1400px; margin: 0 auto; }

        .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 24px; }
        .card-body { padding: 20px 24px; }

        .tabs { display: flex; gap: 0; border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; font-size: 14px; cursor: pointer; border: none; background: none; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn:hover { color: #1890ff; }
        .tab-btn.active { color: #1890ff; border-bottom-color: #1890ff; font-weight: 600; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 12px; color: #888; font-weight: 500; }
        .filter-group input,
        .filter-group select { padding: 6px 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; min-width: 140px; outline: none; transition: border-color 0.2s; background: #fff; }
        .filter-group input:focus,
        .filter-group select:focus { border-color: #1890ff; box-shadow: 0 0 0 2px rgba(24,144,255,0.2); }
        .filter-group input { min-width: 120px; }
        .btn { padding: 7px 16px; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #1890ff; color: #fff; }
        .btn-primary:hover { background: #40a9ff; }
        .btn-danger { background: #ff4d4f; color: #fff; }
        .btn-danger:hover { background: #ff7875; }
        .btn-success { background: #52c41a; color: #fff; }
        .btn-success:hover { background: #73d13d; }
        .btn-default { background: #fff; color: #333; border: 1px solid #d9d9d9; }
        .btn-default:hover { color: #1890ff; border-color: #1890ff; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th { background: #fafafa; font-weight: 600; color: #555; text-align: left; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
        table th.sortable { cursor: pointer; user-select: none; }
        table th.sortable:hover { color: #1890ff; }
        table th i.fa-sort { margin-left: 4px; opacity: 0.3; font-size: 11px; }
        table th.active i.fa-sort { opacity: 1; color: #1890ff; }
        table td { padding: 10px 16px; border-bottom: 1px solid #f0f0f0; }
        table tr:hover td { background: #fafafa; }
        .col-id { width: 60px; color: #999; }
        .col-user { width: 70px; }
        .col-ip { width: 130px; font-family: monospace; }
        .col-time { width: 160px; white-space: nowrap; }
        .col-actions { width: 100px; text-align: center; }
        .user-agent-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; background: #f0f0f0; }
        .badge-country { background: #e6f7ff; color: #1890ff; }
        .badge-isp { background: #f6ffed; color: #52c41a; }
        .badge-type { background: #f0f0f0; color: #666; }
        .badge-enable { background: #f6ffed; color: #52c41a; }
        .badge-disable { background: #fff2f0; color: #ff4d4f; }
        .badge-warning { background: #fffbe6; color: #faad14; }
        .badge-danger { background: #fff2f0; color: #ff4d4f; }
        .text-muted { color: #999; }

        .pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 0 0; }
        .pagination-info { font-size: 13px; color: #888; }
        .pagination-controls { display: flex; align-items: center; gap: 8px; }
        .pagination-controls button { min-width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
        .pagination-controls span { font-size: 13px; color: #555; padding: 0 4px; }
        .page-size-select { padding: 4px 8px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; outline: none; }

        .loading { text-align: center; padding: 60px 0; color: #999; }
        .loading i { font-size: 32px; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .empty-state { text-align: center; padding: 60px 0; color: #ccc; }
        .empty-state i { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }
        .error-state { text-align: center; padding: 60px 0; color: #ff4d4f; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .toolbar-title { font-size: 15px; font-weight: 600; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: #fff; border-radius: 8px; width: 560px; max-width: 90vw; max-height: 85vh; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #f0f0f0; }
        .modal-header h3 { font-size: 16px; }
        .modal-close { cursor: pointer; font-size: 18px; color: #999; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 20px 24px; }
        .modal-footer { padding: 12px 24px; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; gap: 8px; }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 4px; }
        .form-row input, .form-row select { width: 100%; padding: 7px 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px; outline: none; }
        .form-row input:focus, .form-row select:focus { border-color: #1890ff; box-shadow: 0 0 0 2px rgba(24,144,255,0.2); }
        .form-row .hint { font-size: 11px; color: #999; margin-top: 2px; }
        .form-inline { display: flex; gap: 10px; }
        .form-inline .form-row { flex: 1; }

        .server-host { font-family: monospace; font-size: 12px; color: #1890ff; }
        .arrow { color: #999; margin: 0 6px; }
        .risk-sortable { cursor: pointer; user-select: none; }
        .risk-sortable:hover { color: #1890ff; }
        .risk-sortable i.fa-sort { margin-left: 4px; opacity: 0.3; font-size: 11px; }
        .risk-sortable.active i.fa-sort { opacity: 1; color: #1890ff; }

        @media (max-width: 768px) {
            .filter-group input, .filter-group select { min-width: 100px; }
            .modal { width: 95vw; }
            .form-inline { flex-direction: column; }
        }
    </style>
</head>
<body>
<header class="header">
    <h1>订阅管理</h1>
</header>

<div class="content">
    <div class="card">
        <div class="card-body">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tabLogs">订阅日志</button>
                <button class="tab-btn" data-tab="tabServers">节点地址汇总</button>
                <button class="tab-btn" data-tab="tabRules">替换规则</button>
                <button class="tab-btn" data-tab="tabRisk">风险筛查</button>
            </div>

            <div id="tabLogs" class="tab-content active">
                <div class="filter-form" id="filterForm">
                    <div class="filter-group">
                        <label>User ID</label>
                        <input type="number" id="filterUserId" placeholder="精确搜索" min="1">
                    </div>
                    <div class="filter-group">
                        <label>User-Agent</label>
                        <input type="text" id="filterUserAgent" placeholder="关键字搜索">
                    </div>
                    <div class="filter-group">
                        <label>IP 地址</label>
                        <input type="text" id="filterIp" placeholder="关键字搜索">
                    </div>
                    <div class="filter-group">
                        <label>国家</label>
                        <select id="filterCountry"><option value="">全部</option></select>
                    </div>
                    <div class="filter-group">
                        <label>省份</label>
                        <select id="filterRegion"><option value="">全部</option></select>
                    </div>
                    <div class="filter-group">
                        <label>城市</label>
                        <input type="text" id="filterCity" placeholder="关键字搜索">
                    </div>
                    <div class="filter-group">
                        <label>运营商</label>
                        <select id="filterIsp"><option value="">全部</option></select>
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <div style="display:flex;gap:8px">
                            <button class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i>搜索</button>
                            <button class="btn btn-default" id="resetBtn"><i class="fas fa-undo"></i>重置</button>
                            <button class="btn btn-danger" id="clearBtn"><i class="fas fa-trash"></i>清除</button>
                        </div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-id sortable" data-sort="id">ID <i class="fas fa-sort"></i></th>
                                <th class="col-user sortable" data-sort="user_id">User <i class="fas fa-sort"></i></th>
                                <th class="col-ip sortable" data-sort="ip">IP <i class="fas fa-sort"></i></th>
                                <th data-sort="ip_country">国家</th>
                                <th data-sort="ip_region">省份</th>
                                <th data-sort="ip_city">城市</th>
                                <th data-sort="ip_isp">运营商</th>
                                <th data-sort="user_agent">User-Agent</th>
                                <th class="col-time sortable" data-sort="created_at">时间 <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="9"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">共 0 条</div>
                    <div class="pagination-controls">
                        <span>每页</span>
                        <select class="page-size-select" id="pageSizeSelect">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>条</span>
                        <button class="btn btn-default btn-sm" id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
                        <span id="pageInfo">0/0</span>
                        <button class="btn btn-default btn-sm" id="nextPage" disabled><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div id="tabServers" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">所有节点地址</span>
                    <button class="btn btn-default btn-sm" id="refreshServersBtn"><i class="fas fa-sync"></i>刷新</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>节点名</th>
                                <th>Host</th>
                                <th>端口</th>
                                <th>分组</th>
                            </tr>
                        </thead>
                        <tbody id="serversBody">
                            <tr><td colspan="5"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tabRules" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">替换规则 <span class="text-muted" style="font-weight:400;font-size:12px">按 sort 优先级，取第一条匹配</span></span>
                    <button class="btn btn-primary btn-sm" id="addRuleBtn"><i class="fas fa-plus"></i>添加规则</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:50px">排序</th>
                                <th>备注</th>
                                <th>匹配 UA</th>
                                <th>匹配 国家</th>
                                <th>省份</th>
                                <th>城市</th>
                                <th>运营商</th>
                                <th>匹配 User ID</th>
                                <th>原 Host</th>
                                <th>替换为</th>
                                <th>替换端口</th>
                                <th>状态</th>
                                <th class="col-actions">操作</th>
                            </tr>
                        </thead>
                        <tbody id="rulesBody">
                            <tr><td colspan="13"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tabRisk" class="tab-content">
                <div class="toolbar">
                    <span class="toolbar-title">风险筛查</span>
                </div>
                <div class="filter-form" id="riskFilterForm">
                    <div class="filter-group">
                        <label>城市去重数 ≥</label>
                        <input type="number" id="riskCityThreshold" value="5" min="1">
                    </div>
                    <div class="filter-group">
                        <label>UA 去重数 ≥</label>
                        <input type="number" id="riskUaThreshold" value="3" min="1">
                    </div>
                    <div class="filter-group" style="padding-bottom:1px">
                        <label>&nbsp;</label>
                        <div style="display:flex;gap:8px">
                            <button class="btn btn-primary" id="riskSearchBtn"><i class="fas fa-search"></i>筛查</button>
                        </div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:70px">User ID</th>
                                <th>邮箱</th>
                                <th class="risk-sortable" data-sort="city_count">城市去重 <i class="fas fa-sort"></i></th>
                                <th class="risk-sortable" data-sort="ua_count">UA 去重 <i class="fas fa-sort"></i></th>
                                <th>总请求</th>
                                <th style="width:160px">最后时间</th>
                            </tr>
                        </thead>
                        <tbody id="riskBody">
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-shield-alt"></i><p>点击"筛查"开始分析</p></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="riskPagination" style="display:none">
                    <div class="pagination-info"><span id="riskTotalInfo">共 0 条</span></div>
                    <div class="pagination-controls">
                        <button class="btn btn-default btn-sm" id="riskPrevPage" disabled><i class="fas fa-chevron-left"></i></button>
                        <span id="riskPageInfo">0/0</span>
                        <button class="btn btn-default btn-sm" id="riskNextPage" disabled><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ruleModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="ruleModalTitle">添加规则</h3>
            <span class="modal-close" onclick="closeRuleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ruleId">
            <div class="form-row">
                <label>备注 <span class="text-muted">*</span></label>
                <input type="text" id="ruleRemark" placeholder="例如：国内用户走国内入口">
            </div>
            <div class="form-row">
                <label>排序 <span class="text-muted">*</span></label>
                <input type="number" id="ruleSort" value="0" min="0">
            </div>
            <div class="form-row">
                <label>匹配 User ID <span class="hint">精确匹配，留空=不限制</span></label>
                <input type="text" id="ruleUserId" placeholder="例如：123 或 NOT:5">
            </div>
            <div class="form-row">
                <label>匹配 User-Agent <span class="hint">模糊匹配，留空=不限制</span></label>
                <input type="text" id="ruleUserAgent" placeholder="例如：ClashMeta">
            </div>
            <div class="form-inline">
                <div class="form-row">
                    <label>匹配 国家</label>
                    <input type="text" id="ruleCountry" placeholder="留空=不限制">
                </div>
                <div class="form-row">
                    <label>匹配 省份</label>
                    <input type="text" id="ruleRegion" placeholder="留空=不限制">
                </div>
            </div>
            <div class="form-inline">
                <div class="form-row">
                    <label>匹配 城市</label>
                    <input type="text" id="ruleCity" placeholder="留空=不限制">
                </div>
                <div class="form-row">
                    <label>匹配 运营商</label>
                    <input type="text" id="ruleIsp" placeholder="留空=不限制">
                </div>
            </div>
            <div class="form-row">
                <label>原 Host <span class="hint">留空=替换所有节点</span></label>
                <input type="text" id="ruleOriginalHost" placeholder="例如：sg.example.com">
            </div>
            <div class="form-row">
                <label>替换 Host <span class="text-muted">*</span></label>
                <input type="text" id="ruleReplaceHost" placeholder="例如：hk.example.com">
            </div>
            <div class="form-row">
                <label>替换端口 <span class="hint">留空=不替换端口</span></label>
                <input type="number" id="ruleReplacePort" placeholder="例如：443" min="1" max="65535">
            </div>
            <div class="form-row">
                <label>状态</label>
                <select id="ruleEnable">
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-default" onclick="closeRuleModal()">取消</button>
            <button class="btn btn-primary" id="saveRuleBtn">保存</button>
        </div>
    </div>
</div>

<script>
var securePath = '{{$secure_path}}';
var apiBase = '/api/v1/' + securePath;

var authToken = window.localStorage.getItem('authorization');
if (!authToken) {
    window.location.href = '/' + securePath + '/login';
}

function displayRuleValue(val) {
    if (!val) return '<span class="text-muted">-</span>';
    if (val.startsWith('NOT:')) return '<span class="text-danger">NOT:</span> ' + val.substring(4);
    return val;
}

function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['authorization'] = authToken;
    return fetch(url, options);
}

// =========== Tab switching ===========
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
        document.getElementById(this.getAttribute('data-tab')).classList.add('active');
    });
});

// =========== Log state & functions ===========
var state = { current: 1, pageSize: 10, sort: 'id', sortType: 'DESC', filters: {}, total: 0 };

function fmtTime(ts) {
    if (!ts) return '-';
    var d = new Date(ts * 1000);
    var pad = function(n) { return n < 10 ? '0' + n : n; };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
}

function truncate(str, len) {
    if (!str) return '-';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function loadFilterOptions() {
    apiFetch(apiBase + '/sub_log/getFilterOptions')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.data) return;
            var countrySel = document.getElementById('filterCountry');
            var regionSel = document.getElementById('filterRegion');
            var ispSel = document.getElementById('filterIsp');
            res.data.countries.forEach(function(c) {
                var opt = document.createElement('option');
                opt.value = c; opt.textContent = c;
                countrySel.appendChild(opt);
            });
            res.data.regions.forEach(function(r) {
                var opt = document.createElement('option');
                opt.value = r; opt.textContent = r;
                regionSel.appendChild(opt);
            });
            res.data.isps.forEach(function(i) {
                var opt = document.createElement('option');
                opt.value = i; opt.textContent = i;
                ispSel.appendChild(opt);
            });
        }).catch(function() {});
}

function buildFilters() {
    var f = {};
    var userId = document.getElementById('filterUserId').value.trim();
    if (userId) f.user_id = userId;
    var ua = document.getElementById('filterUserAgent').value.trim();
    if (ua) f.user_agent = ua;
    var ip = document.getElementById('filterIp').value.trim();
    if (ip) f.ip = ip;
    var country = document.getElementById('filterCountry').value;
    if (country) f.ip_country = country;
    var region = document.getElementById('filterRegion').value;
    if (region) f.ip_region = region;
    var city = document.getElementById('filterCity').value.trim();
    if (city) f.ip_city = city;
    var isp = document.getElementById('filterIsp').value;
    if (isp) f.ip_isp = isp;
    return f;
}

function loadLogData() {
    var params = new URLSearchParams();
    params.set('current', state.current);
    params.set('pageSize', state.pageSize);
    params.set('sort', state.sort);
    params.set('sort_type', state.sortType);
    var filters = state.filters;
    Object.keys(filters).forEach(function(k) {
        if (filters[k]) params.set(k, filters[k]);
    });

    document.getElementById('tableBody').innerHTML = '<tr><td colspan="9"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';

    apiFetch(apiBase + '/sub_log/fetch?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(res) {
            state.total = res.total || 0;
            renderLogTable(res.data || []);
        })
        .catch(function(err) {
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="9"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

function renderLogTable(data) {
    var tbody = document.getElementById('tableBody');
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无数据</p></div></td></tr>';
        updatePagination();
        return;
    }
    var html = '';
    data.forEach(function(row) {
        html += '<tr>'
            + '<td class="col-id">' + row.id + '</td>'
            + '<td class="col-user"><a href="javascript:void(0)" onclick="filterByUser(' + row.user_id + ')" style="color:#1890ff">' + row.user_id + '</a></td>'
            + '<td class="col-ip">' + (row.ip || '-') + '</td>'
            + '<td>' + (row.ip_country ? '<span class="badge badge-country">' + row.ip_country + '</span>' : '<span class="text-muted">-</span>') + '</td>'
            + '<td>' + (row.ip_region || '-') + '</td>'
            + '<td>' + (row.ip_city || '-') + '</td>'
            + '<td>' + (row.ip_isp ? '<span class="badge badge-isp">' + row.ip_isp + '</span>' : '<span class="text-muted">-</span>') + '</td>'
            + '<td class="user-agent-cell" title="' + (row.user_agent || '').replace(/"/g, '&quot;') + '">' + truncate(row.user_agent, 40) + '</td>'
            + '<td class="col-time">' + fmtTime(row.created_at) + '</td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    updatePagination();
}

function updatePagination() {
    var total = state.total;
    var page = state.current;
    var pageSize = state.pageSize;
    var totalPages = Math.ceil(total / pageSize) || 1;
    document.getElementById('paginationInfo').textContent = '共 ' + total + ' 条';
    document.getElementById('pageInfo').textContent = page + '/' + totalPages;
    document.getElementById('prevPage').disabled = page <= 1;
    document.getElementById('nextPage').disabled = page >= totalPages;
}

function searchLogs() {
    state.current = 1;
    state.filters = buildFilters();
    loadLogData();
}

function filterByUser(userId) {
    document.getElementById('filterUserId').value = userId;
    state.current = 1;
    state.filters = buildFilters();
    loadLogData();
}

function resetFilters() {
    document.getElementById('filterUserId').value = '';
    document.getElementById('filterUserAgent').value = '';
    document.getElementById('filterIp').value = '';
    document.getElementById('filterCountry').value = '';
    document.getElementById('filterRegion').value = '';
    document.getElementById('filterCity').value = '';
    document.getElementById('filterIsp').value = '';
    state.current = 1;
    state.filters = {};
    loadLogData();
}

// =========== Servers ===========
function loadServers() {
    document.getElementById('serversBody').innerHTML = '<tr><td colspan="5"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';
    apiFetch(apiBase + '/sub_log/getServers')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || [];
            if (data.length === 0) {
                document.getElementById('serversBody').innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无节点</p></div></td></tr>';
                return;
            }
            var html = '';
            data.forEach(function(s) {
                html += '<tr>'
                    + '<td><span class="badge badge-type">' + s.type + '</span></td>'
                    + '<td>' + s.name + '</td>'
                    + '<td class="server-host">' + s.host + '</td>'
                    + '<td>' + s.port + '</td>'
                    + '<td>' + (s.group_id || '-') + '</td>'
                    + '</tr>';
            });
            document.getElementById('serversBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('serversBody').innerHTML = '<tr><td colspan="5"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

// =========== Rules ===========
var editingRuleId = null;

function loadRules() {
    document.getElementById('rulesBody').innerHTML = '<tr><td colspan="13"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';
    apiFetch(apiBase + '/sub_log/getRules')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || [];
            if (data.length === 0) {
                document.getElementById('rulesBody').innerHTML = '<tr><td colspan="13"><div class="empty-state"><i class="fas fa-inbox"></i><p>暂无规则</p></div></td></tr>';
                return;
            }
            var html = '';
            data.forEach(function(r) {
                html += '<tr>'
                    + '<td>' + r.sort + '</td>'
                    + '<td>' + (r.remark || '') + '</td>'
                    + '<td>' + displayRuleValue(r.user_agent) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_country) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_region) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_city) + '</td>'
                    + '<td>' + displayRuleValue(r.ip_isp) + '</td>'
                    + '<td>' + displayRuleValue(r.user_id) + '</td>'
                    + '<td>' + (r.original_host || '<span class="text-muted">全部</span>') + '</td>'
                    + '<td><span class="server-host">' + r.replace_host + '</span></td>'
                    + '<td>' + (r.replace_port || '<span class="text-muted">-</span>') + '</td>'
                    + '<td>' + (r.enable ? '<span class="badge badge-enable">启用</span>' : '<span class="badge badge-disable">禁用</span>') + '</td>'
                    + '<td class="col-actions">'
                    + '<button class="btn btn-default btn-sm" onclick="editRule(' + r.id + ')"><i class="fas fa-edit"></i></button> '
                    + '<button class="btn btn-danger btn-sm" onclick="deleteRule(' + r.id + ')"><i class="fas fa-trash"></i></button>'
                    + '</td></tr>';
            });
            document.getElementById('rulesBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('rulesBody').innerHTML = '<tr><td colspan="13"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

function openRuleModal(rule) {
    editingRuleId = rule ? rule.id : null;
    document.getElementById('ruleModalTitle').textContent = rule ? '编辑规则' : '添加规则';
    document.getElementById('ruleId').value = rule ? rule.id : '';
    document.getElementById('ruleRemark').value = rule ? (rule.remark || '') : '';
    document.getElementById('ruleSort').value = rule ? (rule.sort || 0) : 0;
    document.getElementById('ruleUserAgent').value = rule ? (rule.user_agent || '') : '';
    document.getElementById('ruleUserId').value = rule ? (rule.user_id || '') : '';
    document.getElementById('ruleCountry').value = rule ? (rule.ip_country || '') : '';
    document.getElementById('ruleRegion').value = rule ? (rule.ip_region || '') : '';
    document.getElementById('ruleCity').value = rule ? (rule.ip_city || '') : '';
    document.getElementById('ruleIsp').value = rule ? (rule.ip_isp || '') : '';
    document.getElementById('ruleOriginalHost').value = rule ? (rule.original_host || '') : '';
    document.getElementById('ruleReplaceHost').value = rule ? (rule.replace_host || '') : '';
    document.getElementById('ruleReplacePort').value = rule ? (rule.replace_port || '') : '';
    document.getElementById('ruleEnable').value = rule ? (rule.enable ? '1' : '0') : '1';
    document.getElementById('ruleModal').classList.add('show');
}

function closeRuleModal() {
    document.getElementById('ruleModal').classList.remove('show');
}

function editRule(id) {
    apiFetch(apiBase + '/sub_log/getRules')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var rules = res.data || [];
            var rule = rules.find(function(r) { return r.id === id; });
            if (rule) openRuleModal(rule);
        });
}

function deleteRule(id) {
    if (!confirm('确认删除此规则？')) return;
    apiFetch(apiBase + '/sub_log/dropRule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(function(r) { return r.json(); })
    .then(function() { loadRules(); })
    .catch(function() { alert('删除失败'); });
}

document.getElementById('addRuleBtn').addEventListener('click', function() { openRuleModal(null); });
document.getElementById('saveRuleBtn').addEventListener('click', function() {
    var data = {
        id: editingRuleId || null,
        remark: document.getElementById('ruleRemark').value.trim(),
        sort: parseInt(document.getElementById('ruleSort').value) || 0,
        user_agent: document.getElementById('ruleUserAgent').value.trim() || null,
        user_id: document.getElementById('ruleUserId').value.trim() || null,
        ip_country: document.getElementById('ruleCountry').value.trim() || null,
        ip_region: document.getElementById('ruleRegion').value.trim() || null,
        ip_city: document.getElementById('ruleCity').value.trim() || null,
        ip_isp: document.getElementById('ruleIsp').value.trim() || null,
        original_host: document.getElementById('ruleOriginalHost').value.trim() || null,
        replace_host: document.getElementById('ruleReplaceHost').value.trim(),
        replace_port: parseInt(document.getElementById('ruleReplacePort').value) || null,
        enable: parseInt(document.getElementById('ruleEnable').value)
    };
    if (!data.replace_host) { alert('替换 Host 不能为空'); return; }
    if (!data.remark) { alert('备注不能为空'); return; }

    apiFetch(apiBase + '/sub_log/saveRule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(function(r) { return r.json(); })
    .then(function() { closeRuleModal(); loadRules(); })
    .catch(function() { alert('保存失败'); });
});

// =========== Tab switch data loading ===========
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tab = this.getAttribute('data-tab');
        if (tab === 'tabServers') loadServers();
        if (tab === 'tabRules') loadRules();
        if (tab === 'tabRisk') loadRiskData();
    });
});

document.getElementById('refreshServersBtn').addEventListener('click', loadServers);

// =========== Risk Screening ===========
var riskState = { current: 1, pageSize: 10, total: 0, sort: 'city_count', sortType: 'DESC' };

function riskUserLink(userId) {
    window.open('/' + securePath + '/sub_log?user_id=' + userId, '_blank');
}

function loadRiskData() {
    var cityThreshold = document.getElementById('riskCityThreshold').value || 5;
    var uaThreshold = document.getElementById('riskUaThreshold').value || 3;

    document.getElementById('riskBody').innerHTML = '<tr><td colspan="6"><div class="loading"><i class="fas fa-spinner"></i><p>加载中...</p></div></td></tr>';

    var params = new URLSearchParams();
    params.set('current', riskState.current);
    params.set('pageSize', riskState.pageSize);
    params.set('city_threshold', cityThreshold);
    params.set('ua_threshold', uaThreshold);
    params.set('sort', riskState.sort);
    params.set('sort_type', riskState.sortType);

    apiFetch(apiBase + '/sub_log/riskCheck?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var data = res.data || [];
            riskState.total = res.total || 0;
            updateRiskPagination();

            if (data.length === 0) {
                document.getElementById('riskBody').innerHTML = '<tr><td colspan="6"><div class="empty-state"><i class="fas fa-check-circle"></i><p>未发现风险用户</p></div></td></tr>';
                return;
            }

            var html = '';
            data.forEach(function(r) {
                var riskTag = '';
                if (parseInt(r.city_count) >= parseInt(cityThreshold) && parseInt(r.ua_count) >= parseInt(uaThreshold)) {
                    riskTag = ' <span class="badge badge-danger">城市+UA</span>';
                } else if (parseInt(r.city_count) >= parseInt(cityThreshold)) {
                    riskTag = ' <span class="badge badge-warning">城市</span>';
                } else {
                    riskTag = ' <span class="badge badge-warning">UA</span>';
                }
                html += '<tr>'
                    + '<td><a href="javascript:void(0)" onclick="riskUserLink(' + r.user_id + ')" style="color:#1890ff">' + r.user_id + '</a></td>'
                    + '<td>' + (r.email || '') + '</td>'
                    + '<td>' + r.city_count + riskTag + '</td>'
                    + '<td>' + r.ua_count + '</td>'
                    + '<td>' + r.total_count + '</td>'
                    + '<td>' + (r.last_time ? fmtTime(r.last_time) : '-') + '</td>'
                    + '</tr>';
            });
            document.getElementById('riskBody').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('riskBody').innerHTML = '<tr><td colspan="6"><div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>加载失败</p></div></td></tr>';
        });
}

function updateRiskPagination() {
    var pagination = document.getElementById('riskPagination');
    if (riskState.total === 0) { pagination.style.display = 'none'; return; }
    pagination.style.display = 'flex';
    var totalPages = Math.ceil(riskState.total / riskState.pageSize) || 1;
    document.getElementById('riskTotalInfo').textContent = '\u5171 ' + riskState.total + ' \u6761';
    document.getElementById('riskPageInfo').textContent = riskState.current + '/' + totalPages;
    document.getElementById('riskPrevPage').disabled = riskState.current <= 1;
    document.getElementById('riskNextPage').disabled = riskState.current >= totalPages;
}

document.getElementById('clearBtn').addEventListener('click', function() {
    if (!confirm('\u786e\u8ba4\u6e05\u9664\u5f53\u524d\u7b5b\u9009\u6761\u4ef6\u4e0b\u7684\u6240\u6709\u8ba2\u9605\u65e5\u5fd7\uff1f\u6b64\u64cd\u4f5c\u4e0d\u53ef\u6062\u590d\u3002')) return;
    var params = new URLSearchParams();
    var filters = buildFilters();
    Object.keys(filters).forEach(function(k) { params.set(k, filters[k]); });
    apiFetch(apiBase + '/sub_log/clearLogs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).then(function(r) { return r.json(); })
    .then(function() { loadLogData(); loadFilterOptions(); })
    .catch(function() { alert('\u6e05\u9664\u5931\u8d25'); });
});

// =========== Init ===========
document.addEventListener('DOMContentLoaded', function() {
    loadFilterOptions();

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('user_id')) {
        document.getElementById('filterUserId').value = urlParams.get('user_id');
        state.filters = buildFilters();
    }

    loadLogData();

    document.getElementById('searchBtn').addEventListener('click', searchLogs);
    document.getElementById('resetBtn').addEventListener('click', resetFilters);

    document.getElementById('filterUserId').addEventListener('keydown', function(e) { if (e.key === 'Enter') searchLogs(); });
    document.getElementById('filterUserAgent').addEventListener('keydown', function(e) { if (e.key === 'Enter') searchLogs(); });
    document.getElementById('filterIp').addEventListener('keydown', function(e) { if (e.key === 'Enter') searchLogs(); });
    document.getElementById('filterCity').addEventListener('keydown', function(e) { if (e.key === 'Enter') searchLogs(); });

    document.getElementById('prevPage').addEventListener('click', function() {
        if (state.current > 1) { state.current--; loadLogData(); }
    });
    document.getElementById('nextPage').addEventListener('click', function() {
        var totalPages = Math.ceil(state.total / state.pageSize);
        if (state.current < totalPages) { state.current++; loadLogData(); }
    });
    document.getElementById('pageSizeSelect').addEventListener('change', function() {
        state.pageSize = parseInt(this.value);
        state.current = 1;
        loadLogData();
    });

    document.getElementById('riskSearchBtn').addEventListener('click', function() {
        riskState.current = 1;
        loadRiskData();
    });
    document.getElementById('riskCityThreshold').addEventListener('keydown', function(e) { if (e.key === 'Enter') { riskState.current = 1; loadRiskData(); } });
    document.getElementById('riskUaThreshold').addEventListener('keydown', function(e) { if (e.key === 'Enter') { riskState.current = 1; loadRiskData(); } });
    document.getElementById('riskPrevPage').addEventListener('click', function() {
        if (riskState.current > 1) { riskState.current--; loadRiskData(); }
    });
    document.getElementById('riskNextPage').addEventListener('click', function() {
        var totalPages = Math.ceil(riskState.total / riskState.pageSize);
        if (riskState.current < totalPages) { riskState.current++; loadRiskData(); }
    });
    document.querySelectorAll('.risk-sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var key = this.getAttribute('data-sort');
            if (riskState.sort === key) {
                riskState.sortType = riskState.sortType === 'DESC' ? 'ASC' : 'DESC';
            } else {
                riskState.sort = key;
                riskState.sortType = 'DESC';
            }
            document.querySelectorAll('.risk-sortable').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            riskState.current = 1;
            loadRiskData();
        });
    });

    document.querySelectorAll('th.sortable').forEach(function(th) {
        th.addEventListener('click', function() {
            var key = this.getAttribute('data-sort');
            if (state.sort === key) {
                state.sortType = state.sortType === 'DESC' ? 'ASC' : 'DESC';
            } else {
                state.sort = key;
                state.sortType = 'DESC';
            }
            document.querySelectorAll('th.sortable').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            loadLogData();
        });
    });
});
</script>
</body>
</html>
