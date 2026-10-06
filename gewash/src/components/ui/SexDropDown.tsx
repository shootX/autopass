import { motion } from "framer-motion";

export function SexDropDown({
  open,
  setOpen,
  currentSex,
  applySex,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  currentSex: "male" | "female" | "";
  applySex: (sex: "male" | "female") => void;
}) {
  if (!open) return null;

  return (
    <>
      <div
        style={{
          position: "fixed",
          inset: 0,
          backgroundColor: "rgba(24,61,105,0.5)",
          zIndex: 999,
          paddingTop: "16px",
        }}
      />
      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(e, info) => {
          if (info.offset.y > 60) setOpen(false);
        }}
        onUpdate={(latest) => {
          if (latest.y < 0) latest.y = 0;
        }}
        style={{
          position: "fixed",
          left: 0,
          right: 0,
          bottom: 0,
          zIndex: 1000,
          backgroundColor: "#fff",
          borderTopLeftRadius: "16px",
          borderTopRightRadius: "16px",
          minHeight: "200px",
          overflowY: "auto",
          boxShadow: "0 0 20px rgba(0,0,0,0.1)",
          cursor: "grab",
          padding: "16px",
          width: "100%",
        }}
      >
        <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
          <div
            style={{
              display: "flex",
              justifyContent: "center",
              marginTop: "16px",
            }}
          >
            <div
              style={{
                height: "6px",
                width: "40px",
                borderRadius: "9999px",
                backgroundColor: "#D1D5DB",
                marginBottom: "16px",
              }}
            />
          </div>

          <h3
            style={{
              textAlign: "center",
              fontSize: "20px",
              fontWeight: 600,
              color: "#183D69",
              padding: "0px",
            }}
          >
            Choose your sex
          </h3>

          <div
            style={{
              display: "flex",
              gap: "16px",
              justifyContent: "center",
              alignItems: "center",
            }}
          >
            {/* Male */}
            <div
              onClick={() => applySex("male")}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "8px",
                padding: "10px",
                paddingLeft: "12px",
                paddingRight: "12px",
                borderRadius: "16px",
                cursor: "pointer",
                backgroundColor: currentSex === "male" ? "#F7B233" : "#183D69",
                transition: "background-color 0.3s",
              }}
            >
              <svg
                width='24'
                height='24'
                viewBox='0 0 24 24'
                fill='none'
                xmlns='http://www.w3.org/2000/svg'
              >
                <path
                  d='M17.3333 0.333374C17.0681 0.333374 16.8137 0.438731 16.6262 0.626267C16.4387 0.813803 16.3333 1.06816 16.3333 1.33337C16.3333 1.59859 16.4387 1.85294 16.6262 2.04048C16.8137 2.22802 17.0681 2.33337 17.3333 2.33337H20.2466L14.9373 7.62404C13.1374 6.19178 10.8574 5.50305 8.56555 5.69929C6.27371 5.89554 4.14399 6.96185 2.61377 8.67926C1.08355 10.3967 0.268981 12.6348 0.337355 14.934C0.405729 17.2332 1.35185 19.419 2.98142 21.0424C4.611 22.6659 6.80032 23.6037 9.09977 23.6634C11.3992 23.7232 13.6343 22.9002 15.3459 21.3635C17.0575 19.8268 18.1158 17.6931 18.3034 15.4005C18.491 13.1079 17.7937 10.8305 16.3546 9.03604L21.6666 3.74271V6.66671C21.6666 6.93192 21.772 7.18628 21.9595 7.37381C22.1471 7.56135 22.4014 7.66671 22.6666 7.66671C22.9319 7.66671 23.1862 7.56135 23.3737 7.37381C23.5613 7.18628 23.6666 6.93192 23.6666 6.66671V1.33337C23.6666 1.06816 23.5613 0.813803 23.3737 0.626267C23.1862 0.438731 22.9319 0.333374 22.6666 0.333374H17.3333Z'
                  fill={currentSex === "male" ? "#183D69" : "#F7B233"}
                />
              </svg>
            </div>
            <span
              style={{
                fontSize: "16px",
                fontWeight: 500,
                color: currentSex === "male" ? "#F7B233" : "#183D69",
              }}
            >
              Male
            </span>

            {/* Female */}
            <div
              onClick={() => applySex("female")}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "8px",
                padding: "8px",
                paddingLeft: "12px",
                paddingRight: "12px",
                borderRadius: "16px",
                cursor: "pointer",
                backgroundColor:
                  currentSex === "female" ? "#F7B233" : "#183D69",
                transition: "background-color 0.3s",
              }}
            >
              <svg
                width='27'
                height='27'
                viewBox='0 0 27 27'
                fill='none'
                xmlns='http://www.w3.org/2000/svg'
              >
                <path
                  d='M6.33339 10.6667C6.33368 8.40992 7.09733 6.21957 8.5002 4.45178C9.90307 2.68399 11.8627 1.4427 14.0604 0.92973C16.2581 0.416759 18.5647 0.662269 20.6053 1.62634C22.6458 2.59042 24.3002 4.21637 25.2995 6.23985C26.2989 8.26332 26.5844 10.5653 26.1096 12.7716C25.6349 14.9779 24.4278 16.9588 22.6846 18.3921C20.9414 19.8255 18.7646 20.627 16.5082 20.6665C14.2517 20.7059 12.0483 19.9809 10.2561 18.6094L7.87206 20.9921L9.77339 22.8934C9.90074 23.0164 10.0023 23.1635 10.0722 23.3262C10.1421 23.4889 10.1789 23.6638 10.1804 23.8409C10.1819 24.0179 10.1482 24.1935 10.0812 24.3573C10.0141 24.5212 9.91511 24.6701 9.78992 24.7953C9.66473 24.9204 9.51586 25.0194 9.352 25.0865C9.18814 25.1535 9.01256 25.1873 8.83552 25.1857C8.65848 25.1842 8.48353 25.1474 8.32085 25.0775C8.15818 25.0076 8.01106 24.9061 7.88806 24.7787L5.98673 22.8787L3.16006 25.7054C2.90987 25.9554 2.57061 26.0958 2.21692 26.0957C1.86323 26.0955 1.52407 25.9549 1.27406 25.7047C1.02405 25.4545 0.883664 25.1153 0.883789 24.7616C0.883914 24.4079 1.02454 24.0687 1.27473 23.8187L4.10139 20.9921L2.23073 19.1214C2.10338 18.9984 2.0018 18.8513 1.93192 18.6886C1.86205 18.5259 1.82526 18.351 1.82373 18.1739C1.82219 17.9969 1.85592 17.8213 1.92296 17.6575C1.99 17.4936 2.08901 17.3447 2.2142 17.2195C2.33939 17.0943 2.48826 16.9953 2.65212 16.9283C2.81598 16.8613 2.99156 16.8275 3.16859 16.8291C3.34563 16.8306 3.52059 16.8674 3.68327 16.9373C3.84594 17.0071 3.99306 17.1087 4.11606 17.2361L5.98673 19.1067L8.37339 16.7201C7.04724 14.981 6.33035 12.8537 6.33339 10.6667Z'
                  fill={currentSex === "female" ? "#183D69" : "#F7B233"}
                />
              </svg>
            </div>
            <span
              style={{
                fontSize: "16px",
                fontWeight: 500,
                color: currentSex === "female" ? "#F7B233" : "#183D69",
              }}
            >
              Female
            </span>
          </div>
        </div>
      </motion.div>
    </>
  );
}
