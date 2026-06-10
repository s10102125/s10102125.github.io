<?php
require_once 'includes/functions.php';
require_login();
$page_title = '學期運勢';
$current_page = 'tarot.php';
require_once 'includes/header.php';
?>
<style>
.fortune-wrap { max-width: 680px; margin: 0 auto; }

.fortune-hero {
    background: linear-gradient(135deg, #1a1040 0%, #2d1b69 60%, #0f172a 100%);
    border-radius: 18px; padding: 36px 32px 28px;
    text-align: center; margin-bottom: 28px;
    border: 1px solid rgba(160,130,255,.2);
    position: relative; overflow: hidden;
}
.fortune-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(167,139,250,.15) 0%, transparent 70%);
    pointer-events: none;
}
.fortune-hero-title {
    font-size: 24px; font-weight: 700;
    color: rgba(220,200,255,.95); margin-bottom: 6px; position: relative;
}
.fortune-hero-sub { font-size: 13px; color: rgba(160,130,255,.7); position: relative; }

/* Aspect grid */
.aspect-grid {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 20px;
}
@media(max-width:560px){ .aspect-grid{ grid-template-columns:repeat(2,1fr); } }

.aspect-btn {
    background: var(--bg2); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 14px 8px;
    cursor: pointer; text-align: center;
    transition: .15s; font-family: 'Noto Sans TC', sans-serif;
    display: flex; flex-direction: column; align-items: center; gap: 7px;
    color: var(--text2);
}
.aspect-btn:hover { border-color: var(--accent); transform: translateY(-2px); }
.aspect-btn.active { border-color: var(--accent); background: rgba(91,127,255,.08); color: var(--text); }
.aspect-btn-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
}
.aspect-btn-name { font-size: 13px; font-weight: 600; }
.aspect-btn-desc { font-size: 10.5px; color: var(--text3); }

.draw-btn {
    background: linear-gradient(135deg, #5b7fff, #a78bfa);
    color: white; border: none; border-radius: 10px;
    padding: 10px 24px; font-size: 14px; font-weight: 600;
    cursor: pointer; font-family: 'Noto Sans TC', sans-serif;
    transition: opacity .2s; display: inline-flex; align-items: center; gap: 7px;
}
.draw-btn:hover { opacity: .85; }
.draw-again-btn {
    background: transparent; border: 1px solid var(--border);
    color: var(--text2); border-radius: 10px;
    padding: 10px 18px; font-size: 13px;
    cursor: pointer; font-family: 'Noto Sans TC', sans-serif; transition: .15s;
}
.draw-again-btn:hover { border-color: var(--accent); color: var(--accent); }

/* Result card */
.fortune-result {
    background: linear-gradient(160deg, rgba(26,16,64,.8), rgba(45,27,105,.45));
    border: 1px solid rgba(160,130,255,.25);
    border-radius: 16px; padding: 28px 24px;
    display: none; text-align: center;
}
.fortune-result.visible { display: block; animation: fadeUp .45s ease; }
@keyframes fadeUp {
    from { opacity:0; transform:translateY(16px); }
    to   { opacity:1; transform:translateY(0); }
}

.fortune-aspect-label {
    font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
    color: rgba(160,130,255,.6); text-transform: uppercase; margin-bottom: 18px;
}

/* Big verdict */
.fortune-verdict {
    font-size: 48px; font-weight: 900; letter-spacing: 2px;
    margin-bottom: 6px; line-height: 1;
}
.fortune-verdict-sub {
    font-size: 13px; color: rgba(180,160,255,.65);
    margin-bottom: 20px; letter-spacing: 1px;
}

/* Stars row */
.fortune-stars {
    display: flex; gap: 6px; justify-content: center; margin-bottom: 20px;
}
.fortune-star-item {
    width: 22px; height: 22px;
}
.fortune-star-item svg { display: block; }

/* Tags */
.fortune-tags {
    display: flex; flex-wrap: wrap; gap: 8px;
    justify-content: center; margin-bottom: 20px;
}
.fortune-tag {
    font-size: 12px; padding: 4px 12px; border-radius: 20px;
    font-weight: 600; letter-spacing: .3px;
}

/* Divider */
.fortune-divider {
    border: none; border-top: 1px solid rgba(160,130,255,.15);
    margin: 18px 0;
}

/* Message */
.fortune-msg {
    font-size: 14px; color: var(--text2);
    line-height: 1.85; text-align: left;
}

/* Lucky row */
.fortune-lucky {
    margin-top: 18px;
    background: rgba(251,191,36,.07);
    border: 1px solid rgba(251,191,36,.2);
    border-radius: 10px; padding: 12px 16px;
    font-size: 13px; color: rgba(251,191,36,.9);
    display: flex; gap: 10px; align-items: flex-start; text-align: left;
}
.fortune-lucky-title { font-weight: 700; white-space: nowrap; margin-right: 4px; }

/* particles */
.star-particle {
    position: fixed; pointer-events: none; z-index: 9999;
    width: 5px; height: 5px; border-radius: 50%;
    animation: starFly 1.1s ease-out forwards;
}
@keyframes starFly {
    0%   { opacity:1; transform:translate(0,0) scale(1); }
    100% { opacity:0; transform:translate(var(--tx),var(--ty)) scale(0); }
}
</style>

<div class="fortune-wrap">

    <div class="fortune-hero">
        <div class="fortune-hero-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="vertical-align:-3px;margin-right:8px;opacity:.85">
                <path d="M12 2L14.4 8.6H21.6L15.6 12.8L18 19.4L12 15.2L6 19.4L8.4 12.8L2.4 8.6H9.6L12 2Z"
                      stroke="rgba(200,180,255,.9)" stroke-width="1.4" stroke-linejoin="round" fill="rgba(167,139,250,.15)"/>
            </svg>
            學期運勢占卜
        </div>
        <div class="fortune-hero-sub">選擇面向，抽一支學期籤</div>
    </div>

    <!-- Aspect picker -->
    <div class="aspect-grid" id="aspectGrid">
        <?php
        $aspects = [
            ['key'=>'整體運勢',  'icon'=>'home',       'color'=>'#a78bfa', 'desc'=>'本學期整體走向'],
            ['key'=>'課業學習',  'icon'=>'courses',    'color'=>'#5b7fff', 'desc'=>'讀書、考試、理解力'],
            ['key'=>'人際關係',  'icon'=>'attendance', 'color'=>'#f472b6', 'desc'=>'朋友、師長、社交'],
            ['key'=>'健康精力',  'icon'=>'pomodoro',   'color'=>'#34d399', 'desc'=>'體力、睡眠、壓力'],
            ['key'=>'財運',      'icon'=>'grades',     'color'=>'#fbbf24', 'desc'=>'獎學金、打工、花費'],
            ['key'=>'戀愛緣分',  'icon'=>'notes',      'color'=>'#fb7185', 'desc'=>'感情與緣分走向'],
        ];
        foreach ($aspects as $i => $a):
        ?>
        <button class="aspect-btn <?= $i===0?'active':'' ?>"
                data-aspect="<?= h($a['key']) ?>"
                onclick="selectAspect(this)"
                style="--c:<?= $a['color'] ?>">
            <div class="aspect-btn-icon" style="background:<?= $a['color'] ?>22;color:<?= $a['color'] ?>">
                <?= svg_icon($a['icon'], 20) ?>
            </div>
            <div class="aspect-btn-name"><?= h($a['key']) ?></div>
            <div class="aspect-btn-desc"><?= h($a['desc']) ?></div>
        </button>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:10px;align-items:center;margin-bottom:24px">
        <button class="draw-btn" id="drawBtn" onclick="drawFortune()">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                <path d="M8 2L9.2 5.8H13.2L10 8.2L11.2 12L8 9.6L4.8 12L6 8.2L2.8 5.8H6.8L8 2Z"
                      stroke="white" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            抽籤
        </button>
        <button class="draw-again-btn" id="againBtn" style="display:none" onclick="resetFortune()">再抽一次</button>
    </div>

    <!-- Result -->
    <div class="fortune-result" id="fortuneResult">
        <div class="fortune-aspect-label" id="resultLabel"></div>
        <div class="fortune-verdict" id="resultVerdict"></div>
        <div class="fortune-verdict-sub" id="resultSub"></div>
        <div class="fortune-stars" id="resultStars"></div>
        <div class="fortune-tags" id="resultTags"></div>
        <hr class="fortune-divider">
        <div class="fortune-msg" id="resultMsg"></div>
        <div class="fortune-lucky" id="resultLucky">
            <span class="fortune-lucky-title">幸運提示</span>
            <span id="resultLuckyText"></span>
        </div>
    </div>

</div>

<script>
// ── Fortune data ──────────────────────────────────────────────────────────────
const FORTUNES = {
    '整體運勢': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'諸事順遂，把握良機',
          tags:[['課業進展順利','#5b7fff'],['精力充沛','#34d399'],['人緣極佳','#f472b6']],
          msg:'這學期宛如順風行船，你準備好的事都將一一開花結果。不論是課業還是人際，只要主動出擊，收穫遠比你想像的豐盛。保持現在的節奏，不要因為一時的小挫折而動搖。',
          lucky:'多與老師交流，老師這學期特別願意幫你。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'穩健前行，水到渠成',
          tags:[['努力有回報','#5b7fff'],['小心別鬆懈','#fbbf24']],
          msg:'這學期整體走勢良好，只要維持穩定的付出，成果會如期而來。偶有波折，但都在可控範圍內。切記不要在中段鬆懈，學期後半的衝刺非常關鍵。',
          lucky:'每週日整理筆記，有助於期末複習事半功倍。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'平穩可期，細水長流',
          tags:[['按部就班','#a78bfa'],['注意休息','#34d399']],
          msg:'這學期沒有大起大落，是踏實積累的好時機。不求驚喜，但求無悔。把每一堂課都當作投資，學期末回頭看，你會發現自己走了不少路。',
          lucky:'準備一本手帳記錄每日計畫，執行率將大幅提升。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'謹慎應對，靜待轉機',
          tags:[['壓力較大','#ef4444'],['需要調整','#fbbf24'],['靜待時機','#8892aa']],
          msg:'這學期可能會遇到一些阻礙，考試成績、人際摩擦或是身體狀況都可能帶來壓力。不過，困難並非壞事，正是磨練韌性的好機會。遇事冷靜，多向身邊的人求助。',
          lucky:'找一位志同道合的讀書夥伴，互相督促效果顯著。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'留神防範，轉念即是轉機',
          tags:[['課業需加油','#ef4444'],['注意健康','#34d399'],['少惹是非','#8892aa']],
          msg:'這學期挑戰不少，需要比平時多付出一些。別被低潮嚇跑，每一個「凶」簽的背面都藏著提醒：此刻正是你最需要專注的時候。早點開始準備，補課、複習都不嫌早。',
          lucky:'減少熬夜，睡眠充足才能讓思路清晰應對挑戰。' },
    ],
    '課業學習': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'開竅之年，讀什麼懂什麼',
          tags:[['理解力超強','#5b7fff'],['考試手感好','#22c55e'],['效率極佳','#a78bfa']],
          msg:'這學期你的學習狀態達到頂峰，知識吸收速度快、理解深度高。不論是難懂的理論還是繁雜的計算，只要靜下心來都能突破。善用這股氣勢，衝刺你最想提升的科目。',
          lucky:'嘗試教別人，輸出知識是最強的複習方式。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'腳踏實地，成績穩定上升',
          tags:[['認真有回報','#5b7fff'],['平時分拿好','#fbbf24']],
          msg:'這學期只要規律用功，成績就會穩定反映你的努力。不需要什麼奇蹟，就是把作業做好、上課專心，期末自然不會讓你失望。平時小考千萬別輕敵。',
          lucky:'考前兩週開始複習，比臨時抱佛腳有效三倍。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'尚可，多一點努力更穩',
          tags:[['努力才有回報','#a78bfa'],['某科需加強','#fbbf24']],
          msg:'這學期課業表現中規中矩，有幾科表現不錯，但也有一兩科需要補強。建議列出弱點科目，制訂加強計畫。期中考前是最佳調整窗口，別等到期末才後悔。',
          lucky:'去找老師的 office hour，問問題比自己猜有效多了。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'留神，某科有危機',
          tags:[['出席要顧好','#ef4444'],['作業不能漏','#fbbf24'],['可以過關','#8892aa']],
          msg:'這學期至少有一科成績讓你捏一把冷汗。現在亡羊補牢還來得及，把上課出席率顧好、作業一份都不缺交，再把重點章節複習一輪，及格線是守得住的。',
          lucky:'找同學組讀書小組，互相檢查盲點很有幫助。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'警示！快去讀書',
          tags:[['危險科目多','#ef4444'],['需要立刻行動','#ef4444'],['別放棄','#a78bfa']],
          msg:'課業壓力這學期相當沉重，若繼續現在的步調，期末可能面臨危機。現在最重要的事是：停止拖延，從今天開始每天至少讀一小時。老師和助教都是你的資源，去問！',
          lucky:'關掉手機通知，每天專注讀書 90 分鐘，你會驚訝自己的進步。' },
    ],
    '人際關係': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'人緣爆棚，貴人不斷',
          tags:[['魅力四射','#f472b6'],['貴人相助','#a78bfa'],['廣結善緣','#22c55e']],
          msg:'這學期你的人際磁場處於最佳狀態，身邊好人緣自然聚集。不管是課堂上的組員、社團的夥伴還是新認識的朋友，都可能成為一生的貴人。保持真誠待人，這份好運就能持續。',
          lucky:'主動參加一個課外活動，會認識到很合得來的人。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'友情穩固，相處融洽',
          tags:[['朋友圈穩定','#f472b6'],['合作順利','#5b7fff']],
          msg:'這學期人際關係平順，和朋友之間相處和諧，合作型的作業或報告都能順利進行。偶爾有小摩擦，但溝通一下就能化解。珍惜現有的朋友圈，多一點關心就能加深情誼。',
          lucky:'約一個許久沒聯絡的老朋友喝咖啡，緣分會重新燃起。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'平穩，主動一點更好',
          tags:[['主動破冰','#f472b6'],['避免誤解','#fbbf24']],
          msg:'這學期人際關係平平，不特別差但也沒特別出色。如果你一直等別人來找你，可能就這樣低調地過完一學期。試著主動一點，打個招呼、一起吃飯，關係自然就近了。',
          lucky:'在課堂上主動回答問題，會讓同學對你印象加分。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'留意摩擦，少說多聽',
          tags:[['避免衝突','#ef4444'],['少說多聽','#8892aa'],['靜觀其變','#fbbf24']],
          msg:'這學期人際上可能有些小波瀾，例如朋友誤解、組員意見不合等。遇事先冷靜，少講一點爭辯的話，多聽對方說完再回應，很多衝突其實都可以避免。',
          lucky:'有事直接說清楚，千萬不要靠傳話，容易誤會。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'謹言慎行，防小人',
          tags:[['防口舌之爭','#ef4444'],['遠離是非','#8892aa'],['專注自己','#5b7fff']],
          msg:'這學期人際關係較複雜，可能出現誤解、流言或是朋友間的嫌隙。最好的策略是：減少不必要的閒言閒語，做好自己的事，讓時間來證明。不必強求每個人都喜歡你。',
          lucky:'把精力放在學業上，遠比捲入人際風波更值得。' },
    ],
    '健康精力': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'精力旺盛，狀態滿格',
          tags:[['元氣充沛','#34d399'],['睡眠品質佳','#5b7fff'],['壓力自如','#a78bfa']],
          msg:'這學期你的身心狀態達到最佳，精力充沛、頭腦清晰，面對課業壓力也能從容應對。好好利用這股精力衝刺重要目標，同時也不要忘記維持規律作息，讓好狀態延續整個學期。',
          lucky:'嘗試運動 30 分鐘當作讀書前的暖身，效率會更高。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'狀態不錯，維持即可',
          tags:[['體力充足','#34d399'],['注意休息','#fbbf24']],
          msg:'這學期整體健康狀況良好，偶爾疲累但休息一下就能恢復。只要維持規律的作息、不要過度熬夜，這股好狀態可以撐到學期末。注意別因為一時放鬆而打亂整個節律。',
          lucky:'每天睡前把隔天的功課確認一遍，睡得踏實，精力更好。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'尚可，多注意休息',
          tags:[['睡眠需改善','#fbbf24'],['壓力要排解','#a78bfa']],
          msg:'這學期精力時好時壞，可能會出現幾次「突然很累」的情況。這通常是作息不規律或壓力累積的訊號。試著早睡半小時、每週找一個完全放鬆的下午，對整體狀態很有幫助。',
          lucky:'手機設定晚上 11 點勿擾，這是最便宜的健康投資。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'注意身體，別硬撐',
          tags:[['容易疲勞','#ef4444'],['免疫力需顧','#fbbf24'],['早點睡','#34d399']],
          msg:'這學期身體可能會發出一些警訊：容易感冒、頭痛或莫名疲倦。這些都是在提醒你，身體的底線快到了。課業再忙，也要給自己基本的睡眠和飲食，病倒才是真的跟不上。',
          lucky:'多喝水，認真的，很多疲勞都只是缺水造成的。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'警示！好好照顧自己',
          tags:[['健康第一','#ef4444'],['立刻調整作息','#ef4444'],['求助沒關係','#34d399']],
          msg:'這學期健康方面需要認真對待。長期熬夜、飲食不規律、壓力過大，這些都是大忌。如果已經感覺明顯不舒服，請立刻就醫，不要拖。健康是一切的根本，課業可以補，身體垮了才真的麻煩。',
          lucky:'學校的諮商中心是免費資源，壓力大的時候可以去聊聊。' },
    ],
    '財運': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'財運亨通，好事連連',
          tags:[['獎學金有望','#fbbf24'],['意外之財','#22c55e'],['花費順利','#5b7fff']],
          msg:'這學期財運極佳，可能有獎學金、打工加薪或是意想不到的收入出現。但財運好不代表可以亂花，趁這個時機存一筆小緊急基金，未來遇到急需用錢時會很感謝現在的自己。',
          lucky:'把 10% 的零花錢存起來，財運愈好愈要穩。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'收支平穩，小有餘裕',
          tags:[['收支正常','#fbbf24'],['省吃不用省','#22c55e']],
          msg:'這學期財務狀況穩健，沒有大起大落，該有的都會有。在能力範圍內偶爾犒賞自己是沒問題的，但記得別讓花費失控。月初做一個簡單的預算，心裡比較踏實。',
          lucky:'自己煮一兩餐，既省錢又有成就感。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'平平，量入為出',
          tags:[['謹慎消費','#fbbf24'],['記帳有益','#5b7fff']],
          msg:'這學期財務剛好平衡，沒什麼多餘，但也不至於捉襟見肘。盡量避免衝動消費，尤其是那些「看起來便宜其實不需要」的東西。記帳一週，你就會知道錢都跑哪去了。',
          lucky:'比較不同超市的特價，每週可以省下一頓飯的錢。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'留意支出，別透支',
          tags:[['支出偏多','#ef4444'],['少衝動消費','#fbbf24'],['打工可考慮','#22c55e']],
          msg:'這學期財務壓力稍大，可能出現意外支出或是花費失控的情況。現在開始記帳還不晚，先把不必要的訂閱取消，再看看有沒有可以增加收入的方式。量入為出是這學期的財運關鍵。',
          lucky:'整理一下二手平台，把用不到的東西賣掉，小賺一筆。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'財務警示，謹慎為要',
          tags:[['避免借貸','#ef4444'],['緊縮開支','#fbbf24'],['尋求補助','#5b7fff']],
          msg:'這學期財運不佳，可能面臨意外花費或是收入不穩的壓力。最重要的是：千萬不要借貸消費、不要輕信投資話術。如果真的很拮据，學校有生活補助或急難救助，去問問不丟臉。',
          lucky:'向學校輔導組詢問可申請的補助資訊，說不定你符合資格。' },
    ],
    '戀愛緣分': [
        { grade:5, verdict:'大吉', color:'#f59e0b', sub:'桃花旺盛，緣分將至',
          tags:[['異性緣極佳','#fb7185'],['感情甜蜜','#f472b6'],['把握機會','#a78bfa']],
          msg:'這學期你的桃花磁場全開，不管是已有對象還是單身，感情方面都有令人期待的發展。已有對象的人，感情將更加深厚；單身的人，很可能在意想不到的場合遇到心動的對象，主動一點！',
          lucky:'參加一個你平常不太會去的活動，緣分就藏在陌生的地方。' },
        { grade:4, verdict:'吉', color:'#22c55e', sub:'感情順遂，甜蜜加溫',
          tags:[['感情穩定','#fb7185'],['互動良好','#f472b6']],
          msg:'這學期感情方面平穩而溫馨，有對象的人可以趁這個時期多增進彼此的了解；單身的人也不用著急，緣分在慢慢靠近，保持自然開放的心態就好。強求來的緣分不長久。',
          lucky:'一起做一件沒做過的事，比再去同一家餐廳更加分。' },
        { grade:3, verdict:'小吉', color:'#5b7fff', sub:'緣分平平，先愛自己',
          tags:[['充實自己','#5b7fff'],['不強求','#a78bfa']],
          msg:'這學期感情緣分平淡，沒有明顯的起伏。這並不是壞事，有時候把精力放在充實自己上，反而更能吸引到對的人。不要因為身邊的人都有對象就焦慮，你的節奏很好。',
          lucky:'培養一個有趣的興趣，充實的人自帶吸引力。' },
        { grade:2, verdict:'末吉', color:'#f59e0b', sub:'感情需溝通，避免誤解',
          tags:[['多溝通','#fb7185'],['避免猜疑','#fbbf24'],['給彼此空間','#8892aa']],
          msg:'這學期感情方面可能出現一些溝通問題或是誤解。有對象的人，記得多說出自己的感受，別讓誤解在心裡堆積；單身的人，現在可能不是感情的最佳時機，先把自己顧好。',
          lucky:'有話直說，猜心思是感情最大的殺手。' },
        { grade:1, verdict:'凶', color:'#ef4444', sub:'感情多波折，冷靜應對',
          tags:[['避免衝動','#ef4444'],['給彼此時間','#8892aa'],['先顧好自己','#5b7fff']],
          msg:'這學期感情方面較多考驗，可能面臨爭吵、冷戰或是感情的轉捩點。不管結果如何，冷靜和尊重是最重要的。不要在情緒激動時做重大決定，給彼此一點時間和空間，往往比硬碰硬更有效。',
          lucky:'獨處的時間很珍貴，釐清自己真正想要的才是關鍵。' },
    ],
};

// star SVGs
function starSVG(filled, color) {
    if (filled) {
        return `<svg viewBox="0 0 20 20" fill="${color}"><path d="M10 1l2.6 5.3 5.9.9-4.3 4.1 1 5.9L10 14.3l-5.2 2.9 1-5.9L1.5 7.2l5.9-.9z"/></svg>`;
    }
    return `<svg viewBox="0 0 20 20" fill="none"><path d="M10 1l2.6 5.3 5.9.9-4.3 4.1 1 5.9L10 14.3l-5.2 2.9 1-5.9L1.5 7.2l5.9-.9z" stroke="rgba(160,130,255,.3)" stroke-width="1.2"/></svg>`;
}

let currentAspect = '整體運勢';

function selectAspect(el) {
    document.querySelectorAll('.aspect-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    currentAspect = el.dataset.aspect;
}

function drawFortune() {
    const pool = FORTUNES[currentAspect];
    // weighted: grade 5 = weight 1, 4 = 2, 3 = 3, 2 = 2, 1 = 1
    const weights = [1, 2, 3, 2, 1];
    let total = weights.reduce((a,b)=>a+b,0), r = Math.random()*total, idx=0;
    for (let i=0;i<weights.length;i++){ r-=weights[i]; if(r<=0){idx=i;break;} }
    const f = pool[idx];

    // particles
    const btn = document.getElementById('drawBtn');
    burst(btn, f.color);

    // fill result
    document.getElementById('resultLabel').textContent = currentAspect + ' · 學期運勢';
    document.getElementById('resultVerdict').textContent = f.verdict;
    document.getElementById('resultVerdict').style.color = f.color;
    document.getElementById('resultSub').textContent = f.sub;

    // stars
    const starsEl = document.getElementById('resultStars');
    starsEl.innerHTML = '';
    for(let i=1;i<=5;i++){
        const d = document.createElement('div');
        d.className = 'fortune-star-item';
        d.innerHTML = starSVG(i <= f.grade, f.color);
        starsEl.appendChild(d);
    }

    // tags
    const tagsEl = document.getElementById('resultTags');
    tagsEl.innerHTML = f.tags.map(([t,c])=>
        `<span class="fortune-tag" style="background:${c}18;color:${c};border:1px solid ${c}40">${t}</span>`
    ).join('');

    document.getElementById('resultMsg').textContent = f.msg;
    document.getElementById('resultLuckyText').textContent = f.lucky;

    const result = document.getElementById('fortuneResult');
    result.classList.remove('visible');
    void result.offsetWidth; // reflow to re-trigger animation
    result.classList.add('visible');

    document.getElementById('againBtn').style.display = '';
    result.scrollIntoView({ behavior:'smooth', block:'start' });
}

function resetFortune() {
    document.getElementById('fortuneResult').classList.remove('visible');
    document.getElementById('againBtn').style.display = 'none';
}

function burst(refEl, color) {
    const rect = refEl.getBoundingClientRect();
    const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
    for (let i=0;i<14;i++){
        const p = document.createElement('div');
        p.className = 'star-particle';
        const angle = (i/14)*Math.PI*2, dist = 50+Math.random()*70;
        p.style.cssText = `left:${cx}px;top:${cy}px;--tx:${Math.cos(angle)*dist}px;--ty:${Math.sin(angle)*dist}px;background:${color};width:${3+Math.random()*4}px;height:${3+Math.random()*4}px`;
        document.body.appendChild(p);
        setTimeout(()=>p.remove(), 1200);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
