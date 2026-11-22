<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>链式代理</title>
    <style>
        :root { --primary: {{ $primary_color }}; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6fb; color: #333; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 28px 16px 60px; }
        .head { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .head h1 { font-size: 22px; margin: 0; }
        .desc { color: #777; font-size: 13px; line-height: 1.7; margin: 0 0 20px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.05); padding: 18px; }
        .rule-head, .rule-row { display: grid; grid-template-columns: 1fr 1fr 190px; gap: 12px; align-items: center; }
        .rule-head { color: #888; font-size: 13px; padding: 0 4px 10px; border-bottom: 1px solid #f0f0f0; }
        .rule-row { padding: 12px 4px; border-bottom: 1px solid #f5f5f5; }
        .rule-row:last-child { border-bottom: none; }
        select { width: 100%; padding: 8px 10px; border: 1px solid #d9d9d9; border-radius: 6px; background: #fff; font-size: 14px; outline: none; }
        select:focus { border-color: var(--primary); }
        select:disabled { background: #fafafa; color: #999; }
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        button { border: 1px solid #d9d9d9; background: #fff; color: #333; padding: 7px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; }
        button.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        button.primary:hover { filter: brightness(1.08); }
        button.danger { color: #ff4d4f; border-color: #ffccc7; }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .add-wrap { text-align: center; margin-top: 18px; }
        .empty { color: #999; text-align: center; padding: 26px 0; font-size: 14px; }
        .tip { margin-top: 16px; padding: 12px 14px; border-radius: 8px; background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; font-size: 14px; display: none; }
        .tip a { color: var(--primary); margin-left: 8px; }
        .error { margin-top: 16px; padding: 12px 14px; border-radius: 8px; background: #fff2f0; border: 1px solid #ffccc7; color: #ff4d4f; font-size: 14px; display: none; }
        .loading { color: #999; text-align: center; padding: 40px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>链式代理</h1>
    </div>
    <p class="desc">该功能仅Mihomo内核可用，建议使用教程内客户端，添加规则后出入口节点会同时计算流量</p>

    <div class="card">
        <div class="rule-head">
            <div>入口</div>
            <div>出口</div>
            <div style="text-align:right">操作</div>
        </div>
        <div id="ruleList"><div class="loading">加载中...</div></div>
        <div class="add-wrap" id="addWrap" style="display:none">
            <button type="button" class="primary" id="addBtn">+ 新增规则</button>
        </div>
    </div>

    <div class="tip" id="saveTip">保存成功，请更新订阅 <a href="/">更新订阅</a></div>
    <div class="error" id="errorTip"></div>
</div>

<script>
(function () {
    var token = window.localStorage.getItem('authorization');
    var apiBase = (window.settings && window.settings.host ? window.settings.host : window.location.origin) + '/api/v1';
    var state = { enabled: false, inNodes: [], outNodes: [], rules: [] };

    function $(id) { return document.getElementById(id); }

    function showError(message) {
        var box = $('errorTip');
        box.textContent = message;
        box.style.display = 'block';
    }

    function hideError() {
        $('errorTip').style.display = 'none';
    }

    function nodeName(nodes, id) {
        for (var i = 0; i < nodes.length; i++) {
            if (String(nodes[i].id) === String(id)) return nodes[i].name;
        }
        return '';
    }

    function fillSelect(select, nodes, selectedId, excludeId, placeholder) {
        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);
        nodes.forEach(function (node) {
            if (excludeId && String(node.id) === String(excludeId)) return;
            var option = document.createElement('option');
            option.value = String(node.id);
            option.textContent = node.name;
            if (selectedId && String(selectedId) === String(node.id)) option.selected = true;
            select.appendChild(option);
        });
    }

    function render() {
        var list = $('ruleList');
        list.innerHTML = '';

        if (!state.rules.length) {
            var empty = document.createElement('div');
            empty.className = 'empty';
            empty.textContent = '暂无规则，点击下方新增规则';
            list.appendChild(empty);
        }

        state.rules.forEach(function (rule, index) {
            var row = document.createElement('div');
            row.className = 'rule-row';

            var entrySel = document.createElement('select');
            var exitSel = document.createElement('select');
            fillSelect(entrySel, state.inNodes, rule.in_node_id, rule.out_node_id, '请选择入口');
            fillSelect(exitSel, state.outNodes, rule.out_node_id, rule.in_node_id, '请选择出口');
            entrySel.disabled = !rule._editing;
            exitSel.disabled = !rule._editing;

            entrySel.addEventListener('change', function () {
                rule.in_node_id = entrySel.value ? parseInt(entrySel.value, 10) : null;
                if (String(rule.in_node_id) === String(rule.out_node_id)) {
                    rule.out_node_id = null;
                }
                render();
            });
            exitSel.addEventListener('change', function () {
                rule.out_node_id = exitSel.value ? parseInt(exitSel.value, 10) : null;
                if (String(rule.out_node_id) === String(rule.in_node_id)) {
                    rule.in_node_id = null;
                }
                render();
            });

            var actions = document.createElement('div');
            actions.className = 'actions';

            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.textContent = '编辑';
            editBtn.disabled = !!rule._editing;
            editBtn.addEventListener('click', function () {
                rule._editing = true;
                render();
            });

            var saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'primary';
            saveBtn.textContent = '保存';
            saveBtn.disabled = !rule._editing;
            saveBtn.addEventListener('click', saveAll);

            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'danger';
            deleteBtn.textContent = '删除';
            deleteBtn.addEventListener('click', function () {
                state.rules.splice(index, 1);
                saveAll();
            });

            actions.appendChild(editBtn);
            actions.appendChild(saveBtn);
            actions.appendChild(deleteBtn);

            row.appendChild(entrySel);
            row.appendChild(exitSel);
            row.appendChild(actions);
            list.appendChild(row);
        });

        $('addWrap').style.display = state.enabled ? 'block' : 'none';
        $('addBtn').disabled = state.rules.length >= 20;
    }

    function addRule() {
        if (state.rules.length >= 20) return;
        state.rules.push({ in_node_id: null, out_node_id: null, _editing: true });
        render();
    }

    function collectRules() {
        var rules = [];
        state.rules.forEach(function (rule) {
            if (!rule.in_node_id || !rule.out_node_id) return;
            if (String(rule.in_node_id) === String(rule.out_node_id)) return;
            rules.push({ in_node_id: rule.in_node_id, out_node_id: rule.out_node_id });
        });
        return rules;
    }

    function saveAll() {
        hideError();
        $('saveTip').style.display = 'none';

        var rules = collectRules();
        if (rules.length !== state.rules.length) {
            showError('请为每条规则选择不同的入口和出口');
            return;
        }

        fetch(apiBase + '/user/dialerProxy/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                authorization: token || ''
            },
            body: JSON.stringify({ rules: rules })
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok) throw new Error(body.message || ('HTTP ' + response.status));
                return body;
            });
        }).then(function () {
            state.rules.forEach(function (rule) { rule._editing = false; });
            render();
            $('saveTip').style.display = 'block';
        }).catch(function (error) {
            showError('保存失败：' + (error && error.message ? error.message : '请重试'));
        });
    }

    function load() {
        if (!token) {
            $('ruleList').innerHTML = '<div class="empty">请先登录</div>';
            return;
        }

        fetch(apiBase + '/user/dialerProxy', {
            headers: { authorization: token }
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok) throw new Error(body.message || ('HTTP ' + response.status));
                return body;
            });
        }).then(function (body) {
            var data = body.data || {};
            state.enabled = !!data.enabled;
            state.inNodes = data.in_nodes || [];
            state.outNodes = data.out_nodes || [];
            state.rules = (data.rules || []).map(function (rule) {
                return {
                    in_node_id: rule.in_node_id,
                    out_node_id: rule.out_node_id,
                    _editing: false
                };
            });

            if (!state.enabled) {
                $('ruleList').innerHTML = '<div class="empty">链式代理未开启</div>';
                return;
            }
            if (!state.inNodes.length || !state.outNodes.length) {
                $('ruleList').innerHTML = '<div class="empty">暂无可用的入口或出口节点</div>';
                return;
            }
            render();
        }).catch(function (error) {
            $('ruleList').innerHTML = '<div class="empty">加载失败</div>';
            showError('加载失败：' + (error && error.message ? error.message : '请刷新重试'));
        });
    }

    $('addBtn').addEventListener('click', addRule);
    load();
})();
</script>
</body>
</html>
