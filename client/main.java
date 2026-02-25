import org.json.JSONArray;
import org.json.JSONObject;

// 这些为基础变量 (已请将第一项的 API 地址替换为你自己的)
private final String[] S = {
    "https://你的域名/praise.php?type=read&uin=",
    "开启/关闭云端互赞", 
    "togglePraise", 
    "云端互赞已关闭", 
    "云端互赞已暂停", 
    "请等待网络连接...", 
    "已为您加入互赞大军！", 
    "code", 
    "200", 
    "cooldown", 
    "user_delay", 
    "data", 
    "429", 
    "user",
    "like_count" 
};

// 随机点击次数区间
private final int[] _bA = {2, 5, 8, 10, 15, 20}; 
// 时间常量 (毫秒)
private final int _c3 = 60000;
private final int _c4 = 1000;
private final int _c5 = 2500;

volatile boolean _r = false;
volatile boolean _t = false;
volatile boolean _f = true;

// 包含总开关
void onLoad() {
    addItem(S[1], S[2]);
    if (!(_r ^ false)) _p(!false);
}

void onUnLoad() {
    _r = false;
    _st(S[3]);
}

public void togglePraise(String groupUin, String uin, int chatType) {
    if (_r) {
        _r = false;
        _st(S[4]);
    } else {
        _p(!false);
    }
}

private void _p(boolean _v1) {
    _r = true; 
    _f = _v1; 
    _st(S[5]);
    if (_t) return;
    _t = true;
    
    new Thread(new Runnable() {
        public void run() {
            try { Thread.sleep(3000); } catch (Exception e) {}
            while (_r) {
                try {
                    if (myUin == null || myUin.trim().isEmpty()) {
                        _s(3000);
                        continue;
                    }
                    String _v2 = httpGet(S[0] + myUin);
                    int _v3 = _c3; 
                    if (_f) {
                        _st(S[6]);
                        _f = false;
                    }
                    if (_v2 != null && !_v2.trim().isEmpty()) _v3 = _proc(_v2);
                    if (_r) _s(_v3 + _rnd(0, 15000));
                } catch (Throwable x) {
                    if (_r) _s(_c3);
                }
            }
            _t = false;
        }
    }).start();
}

private int _proc(String _v4) {
    int _v5 = _c3;
    try {
        JSONObject _v6 = new JSONObject(_v4);
        if (S[8].equals(_v6.optString(S[7]))) {
            _v5 = _v6.optInt(S[9], 605016);
            int _v8 = _v6.optInt(S[10], 15000);
            int _vL = _v6.optInt(S[14], 50); 
            JSONArray _vA = _v6.optJSONArray(S[11]);
            if (_vA != null && _vA.length() > 0) _exec(_vA, _v8, _vL);
        } else {
            _v5 = _v6.optInt(S[9], 605016); 
        }
    } catch (Throwable e) {}
    return _v5;
}

private void _exec(JSONArray _vA, int _vB, int _vL) {
    for (int i = 0; i < _vA.length() && _r; i++) {
        try {
            String _vC = _vA.getJSONObject(i).optString(S[13]);
            if (_vC == null || _vC.isEmpty()) continue;
            int _vD = _vL;
            while (_vD > 0 && _r) {
                int _vE = Math.min(_vD, _bA[_rnd(0, _bA.length)]);
                try {
                    // 此处仅对匹配下发的随机互赞用户
                    sendLike(_vC, _vE);
                } catch (Throwable e) {}
                _vD -= _vE;
                if (_vD > 0) _s(_rnd(_c4, _c5));
            }
            if (i < _vA.length() - 1) _s(_vB + _rnd(0, 5000));
        } catch (Throwable e) {}
    }
}

private void _s(int _vF) {
    int e = 0;
    while (e < _vF && _r) {
        try { 
            Thread.sleep(100);
            e += 100; 
        } catch (Exception ex) { 
            break; 
        }
    }
}

private void _st(String _vG) {
    try { toast(_vG); } catch (Throwable t) {}
}

private int _rnd(int _vH, int _vI) {
    return _vH + (int)(Math.random() * (_vI - _vH));
}
